<?php
// Minimal declarations of the Gibbon core API the module calls, for static analysis only.

namespace Gibbon\Contracts\Services {
    interface Session
    {
        /** @param string|array<string> $keys */
        public function has($keys);

        /** @param string $key */
        public function get($key, $default = null);

        /** @param string|array<string, mixed> $key */
        public function set($key, $value = null);
    }
}

namespace {
    /** @return bool */
    function isActionAccessible($guid, $connection2, $address, $sub = '') {}

    /** @return string|false */
    function getHighestGroupedAction($guid, $address, $connection2) {}

    /** @return string */
    function __($text, $params = [], $options = []) {}
}
