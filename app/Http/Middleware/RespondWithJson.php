<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the admin app reuse the admin panel's controllers: their redirects with a
 * flash message become JSON, and validation errors are returned as JSON.
 */
class RespondWithJson
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // The app is stateless, so flash messages only live for this request.
        config(['session.driver' => 'array']);
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $session = $response->getSession();
        $error = $session?->get('error');
        $status = $session?->get('status');
        $session?->forget(['error', 'status']);

        if ($error !== null) {
            return response()->json(['message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => $status,
            'redirect' => $response->getTargetUrl(),
        ]);
    }
}
