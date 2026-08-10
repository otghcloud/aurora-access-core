<?php

if (! function_exists('nav_active')) {
    /**
     * Return the ' active' CSS class string if the current request matches
     * one or more URL patterns (supports * wildcards, same as Request::is()).
     */
    function nav_active(string|array $patterns): string
    {
        return request()->is($patterns) ? ' active' : '';
    }
}
