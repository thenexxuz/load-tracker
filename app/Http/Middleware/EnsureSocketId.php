<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ensure that the incoming broadcasting authentication request
 * contains a non-null socket_id.  The Pusher library is strongly
 * typed and will throw a TypeError if null is passed, so this
 * middleware validates the presence of the value and casts it to a
 * string before letting the request proceed.
 */
class EnsureSocketId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // broadcasting/auth can be called with GET or POST depending on
        // the client, so we look in all input sources.
        \Log::info('EnsureSocketId middleware running', ['payload' => $request->all()]);

        if (! $request->has('socket_id') || is_null($request->input('socket_id'))) {
            // we specifically check ``has`` + ``null`` because ``filled`` would
            // treat an empty string as missing; some clients may send an empty
            // value which we also want to reject.
            abort(400, 'Missing socket_id');
        }

        // guarantee string type (Pusher PHP expects string)
        $request->merge([
            'socket_id' => (string) $request->input('socket_id'),
        ]);

        return $next($request);
    }
}
