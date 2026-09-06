<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\HashId;

class DecryptHashIds
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $parameterName  Specific parameter to decrypt (optional)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $parameterName = null)
    {
        $route = $request->route();
        if (!$route) {
            return $next($request);
        }

        $parameters = $route->parameters();

        if ($parameterName) {
            // Decrypt specific parameter if provided
            if (isset($parameters[$parameterName])) {
                $this->decryptParameter($request, $parameterName, $parameters[$parameterName]);
            }
        } else {
            // Decrypt all parameters that look like HashIds
            foreach ($parameters as $name => $value) {
                // If it's a string and doesn't look like a numeric ID, try to decrypt it
                if (is_string($value) && !ctype_digit($value)) {
                    $this->decryptParameter($request, $name, $value);
                }
            }
        }

        return $next($request);
    }

    /**
     * Attempt to decrypt a parameter and update the route.
     */
    protected function decryptParameter(Request $request, $name, $value)
    {
        $decoded = HashId::decode($value);
        if ($decoded !== null) {
            $request->route()->setParameter($name, $decoded);
        }
    }
}
