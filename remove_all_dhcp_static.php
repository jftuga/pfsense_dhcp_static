<?php

require_once("/etc/inc/config.inc");
require_once("/etc/inc/util.inc");
require_once("/etc/inc/services.inc");
require_once("/etc/inc/interfaces.inc");

// Load the existing DHCP configuration
global $config;

if (!isset($config['dhcpd'])) {
    fwrite(STDERR, "No DHCP configuration found.\n");
    exit(1);
}

// Remove all static mappings from all interfaces
foreach ($config['dhcpd'] as $interface => &$dhcp_config) {
    if (isset($dhcp_config['staticmap']) && is_array($dhcp_config['staticmap'])) {
        unset($dhcp_config['staticmap']);
        echo "Removed all static DHCP reservations from interface: $interface\n";
    }
}

// Save and apply the new configuration
write_config("Removed all static DHCP reservations");
services_dhcpd_configure();

echo "All static DHCP reservations have been removed and changes applied.\n";
exit(0);
