<?php

if (! function_exists('csp_nonce')) {
    /**
     * Get the CSP nonce for inline scripts.
     *
     * Usage in Blade templates:
     * <script nonce="{{ csp_nonce() }}">
     *     // Your inline JavaScript
     * </script>
     *
     * @return string
     */
    function csp_nonce(): string
    {
        return request()->attributes->get('csp_nonce', '');
    }
}
