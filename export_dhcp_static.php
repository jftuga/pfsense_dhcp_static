<?php

// Include necessary pfSense libraries
require_once("/etc/inc/config.inc");
require_once("/etc/inc/util.inc");
require_once("/etc/inc/services.inc");

// Load pfSense configuration
$config = parse_config(true);

// Open output to STDOUT
$csv_handle = fopen('php://output', 'w');
if (!$csv_handle) {
    die("Error: Unable to open output stream.\n");
}

// Write CSV header
fputcsv($csv_handle, ["mac", "ipaddr", "hostname", "description"]);

// Iterate over all DHCP interfaces (LAN, VLANs, etc.)
if (!empty($config['dhcpd'])) {
    foreach ($config['dhcpd'] as $interface => $dhcp_config) {
        if (!empty($dhcp_config['staticmap'])) {
            foreach ($dhcp_config['staticmap'] as $entry) {
                $mac = $entry['mac'] ?? '';
                $ipaddr = $entry['ipaddr'] ?? '';
                $hostname = $entry['hostname'] ?? '';
                $description = $entry['descr'] ?? '';

                // Write to CSV
                fputcsv($csv_handle, [$mac, $ipaddr, $hostname, $description]);
            }
        }
    }
}

// Close the CSV stream
fclose($csv_handle);
?>
