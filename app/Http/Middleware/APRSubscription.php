<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Closure;

class APRSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(!\Auth::user()->hasRole('superAdmin')) {
            $property_id = ($request->property) ? $request->property : $request->route('property');
            $property = Property::findOrFail($property_id);
            if ($property->company->apr_subscription == 'no') {
                return redirect()->route('amenity_pricing_review.aprSubscriptionError', $property);
            }
        }
        return $next($request);
    }
}
