<?php

namespace Ninja\DeviceTracker\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Ninja\DeviceTracker\Contracts\StorableId;
use Ninja\DeviceTracker\Enums\FinishedSessionBehaviour;
use Ninja\DeviceTracker\Enums\SessionIpChangeBehaviour;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Enums\Transport;
use Ninja\DeviceTracker\Events\SessionLocationChangedEvent;
use Ninja\DeviceTracker\Exception\DeviceNotFoundException;
use Ninja\DeviceTracker\Facades\SessionManager;
use Ninja\DeviceTracker\Factories\SessionIdFactory;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Transports\SessionTransport;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

final readonly class SessionTracker
{
    public function __construct(protected Guard $auth) {}

    public function handle(
        Request $request,
        Closure $next,
        ?string $hierarchyParameterString = null,
        ?string $responseTransport = null,
    ): mixed {
        $this->checkCustomSessionTransportHierarchy($hierarchyParameterString);
        $this->checkCustomSessionResponseTransport($responseTransport);

        $device = device();
        if ($device === null) {
            return $next($request);
        }

        $session = $this->getSession();

        if ($session !== null) {
            if ($session->locked()) {
                return $this->manageLock($request);
            }

            if ($session->blocked()) {
                return $this->manageLogout($request, $session);
            }

            if ($session->finished()) {
                if (config('devices.finished_session_behaviour', FinishedSessionBehaviour::Logout->value) === FinishedSessionBehaviour::StartNew->value) {
                    $session = $this->startNewSession($session);
                } else {
                    return $this->manageLogout($request, $session);
                }
            }

            if ($session->inactive()) {
                return $this->manageInactivity($request, $session, $next);
            }

            if ($this->changedLocation($session)) {
                $session = $this->manageSessionLocationChange($session);
            }

            // Make sure session is kept alive
            $session->restart($request);

            $response = $next($request);

            if (guard()->check()) {
                // The login api could have been called again, get again the session to get the latest active one
                $session = device_session();
                if ($session === null) {
                    return $response;
                }

                return SessionTransport::set($response, $session->uuid);
            }

            return $response;
        }

        if (guard()->check()) {
            try {
                $session = SessionManager::start();
                SessionTransport::propagate($session->uuid);
                $response = $next($request);

                // Auth may change during $next (e.g. logout); re-check guard.
                // @phpstan-ignore if.alwaysTrue (guard state is not constant across $next)
                if (guard()->check()) {
                    return SessionTransport::set($response, $session->uuid);
                }

                // User has done logout, avoid setting session
                return $response; // @phpstan-ignore deadCode.unreachable (guard may change during $next)
            } catch (DeviceNotFoundException $e) {
                Log::error('Failed to start session', ['error' => $e->getMessage()]);
            }
        } else {
            try {
                if (session_uuid() === null) {
                    // Make sure a session uuid is used when doing this so any api can still refer to the session uuid
                    // even when the session has not been created yet due to the api logging in.
                    $sessionUuid = SessionIdFactory::generate();
                    SessionTransport::propagate($sessionUuid);
                }
                $response = $next($request);

                // @phpstan-ignore if.alwaysFalse (login may run inside $next while guard was false at entry)
                if (guard()->check()) {
                    // Here the api must have done the login which sets the session uuid
                    $sessionUuid = session_uuid();

                    if ($sessionUuid instanceof StorableId) {
                        return SessionTransport::set($response, $sessionUuid);
                    }
                }

                return $response;
            } catch (DeviceNotFoundException $e) {
                Log::error('Failed to start session', ['error' => $e->getMessage()]);
            }
        }

        return $next($request);
    }

    private function checkCustomSessionTransportHierarchy(?string $hierarchyParameterString = null): void
    {
        if (! empty($hierarchyParameterString)) {
            $hierarchy = array_filter(
                explode('|', $hierarchyParameterString),
                fn (string $value) => Transport::tryFrom($value) !== null,
            );
            if (! empty($hierarchy)) {
                Config::set('devices.transports.session_id.transport_hierarchy', $hierarchy);
            }
        }
    }

    private function checkCustomSessionResponseTransport(?string $parameterString = null): void
    {
        if (
            ! empty($parameterString)
            && Transport::tryFrom($parameterString) !== null
            && $parameterString !== Transport::Request->value
        ) {
            Config::set('devices.devices.transports.session_id.response_transport', $parameterString);
        }
    }

    private function getSession(): ?Session
    {
        $session = device_session();

        // This could happen if the user has been soft-deleted
        if ($session !== null && $session->user === null) {
            $session->end();
            $session = null;
            SessionTransport::cleanRequest();
            SessionTransport::forget();
        }

        return $session;
    }

    private function manageLogout(Request $request, Session $session): JsonResponse|RedirectResponse
    {
        $user = user();
        $guard = guard();

        if ($user !== null) {
            if ($user->google2faEnabled()) {
                $user->google2fa->last_success_at = null;
                $user->google2fa->save();
            }

            $guard->logout();
        } else {
            $session->end();
        }

        if ($request->ajax() || config('devices.use_redirects') === false) {
            return response()->json(['message' => 'Unauthorized'], config('devices.logout_http_code', 403));
        } else {
            try {
                return redirect()->route(Config::get('devices.login_route_name'));
            } catch (RouteNotFoundException $e) {
                Log::error('Route not found', ['route' => Config::get('devices.login_route_name'), 'exception' => $e]);
            }
        }

        return response()->json(['message' => 'Unauthorized'], config('devices.logout_http_code', 403));
    }

    private function manageInactivity(Request $request, Session $session, Closure $next): JsonResponse|RedirectResponse|Response
    {
        if (Config::get('devices.inactivity_session_behaviour') === 'terminate') {
            return $this->manageLogout($request, $session);
        } else {
            $session->status = SessionStatus::Inactive;
            $session->save();
        }

        return $next($request);
    }

    private function manageLock(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->ajax() || config('devices.use_redirects') === false) {
            return response()->json(['message' => 'Session locked'], config('devices.lock_http_code', 403));
        }

        try {
            return redirect()->route(Config::get('devices.2fa_route_name'));
        } catch (RouteNotFoundException $e) {
            Log::error('Route not found', ['route' => Config::get('devices.2fa_route_name'), 'exception' => $e]);
        }

        return response()->json(['message' => 'Session locked'], config('devices.lock_http_code', 403));
    }

    private function changedLocation(Session $session): bool
    {
        return Session::resolveClientIp() !== $session->ip;
    }

    private function manageSessionLocationChange(Session $session): Session
    {
        if (! $this->changedLocation($session)) {
            return $session;
        }

        $oldSession = $session;
        $oldLocation = $session->location;
        $oldLastActivityAt = $session->last_activity_at;

        $sessionIpChangeBehaviour = config('devices.session_ip_change_behaviour', SessionIpChangeBehaviour::Relocate->value);

        $session = match ($sessionIpChangeBehaviour) {
            SessionIpChangeBehaviour::Relocate->value => $this->relocateSession($session),
            SessionIpChangeBehaviour::StartNew->value => $this->startNewSession($session),
            default => $session,
        };

        event(new SessionLocationChangedEvent(
            oldSession: $oldSession,
            oldLocation: $oldLocation,
            oldFirstActivityAt: $oldSession->started_at,
            oldLastActivityAt: $oldLastActivityAt,
            currentSession: $session,
            currentLocation: $session->location,
            currentFirstActivityAt: $session->started_at,
            currentLastActivityAt: $session->last_activity_at,
        ));

        return $session;
    }

    private function relocateSession(Session $session): Session
    {
        return $session->relocate();
    }

    private function startNewSession(Session $session): Session
    {
        $session->end();
        $session = SessionManager::start($session->user);

        SessionTransport::propagate($session->uuid);

        return $session;
    }
}
