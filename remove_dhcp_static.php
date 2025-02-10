<?php
/**
 * remove_dhcp_static.php
 *
 * This script removes static DHCP mappings based on given IP addresses, MAC addresses, or hostnames.
 * It intelligently determines the type of each argument provided.
 *
 * Tested on pfSense 2.7.2 CE
 */

require_once("/etc/inc/config.inc");
require_once("/etc/inc/services.inc");
require_once("/etc/inc/util.inc");
require_once("/etc/inc/interfaces.inc");

if ($argc < 2) {
    echo "Usage: php remove_dhcp_static.php <ip_or_mac_or_hostname> [<ip_or_mac_or_hostname> ...]\n";
    exit(1);
}

// Load DHCP configuration
global $config;
$dhcp_config = &$config['dhcpd'];

$removed = [];

function is_mac_address($value) {
    return preg_match('/^([0-9A-Fa-f]{2}[:]){5}([0-9A-Fa-f]{2})$/', $value);
}

function is_ip_address($value) {
    return filter_var($value, FILTER_VALIDATE_IP);
}

// Loop through arguments
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];

    foreach ($dhcp_config as $interface => $settings) {
        if (!isset($settings['staticmap']) || !is_array($settings['staticmap'])) {
            continue;
        }

        foreach ($settings['staticmap'] as $key => $entry) {
            if (
                (is_mac_address($arg) && strcasecmp($entry['mac'], $arg) == 0) ||
                (is_ip_address($arg) && $entry['ipaddr'] == $arg) ||
                (!is_mac_address($arg) && !is_ip_address($arg) && isset($entry['hostname']) && strcasecmp($entry['hostname'], $arg) == 0)
            ) {
                unset($dhcp_config[$interface]['staticmap'][$key]);
                $removed[] = "$arg (from interface: $interface)";
            }
        }
    }
}

if (empty($removed)) {
    echo "No matching DHCP reservations found.\n";
    exit(0);
}

// Save and apply configuration
write_config("Removed static DHCP mappings");
services_dhcpd_configure();

foreach ($removed as $entry) {
    echo "Removed: $entry\n";
}

echo "DHCP changes applied.\n";
exit(0);
