<?php
$want_debug_mode = false;
if ($want_debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

require_once("/etc/inc/config.inc");
require_once("functions.inc");
require_once("util.inc");
require_once("interfaces.inc");
require_once("services.inc");

// Ensure a CSV file path is provided as an argument
if ($argc < 2) {
    die("Usage: php add_dhcp_static.php <path_to_csv_file>\n");
}

// Check for the --allow flag
$n=1;
$allow_in_range = in_array('--allow', $argv);
if($allow_in_range) {
    $n++;
}

$csv_file = $argv[$n]; // Get file path from command-line argument
echo "Starting DHCP static mapping import...\n";
echo "CSV file: $csv_file\n";
flush();

// Check if file exists and is readable
if (!file_exists($csv_file) || !is_readable($csv_file)) {
    die("Error: CSV file not found or not readable at $csv_file\n");
}

// Function to check if an IP address already has a static assignment
function ip_exists($ipaddr, $interface) {
    global $config;
    foreach ($config['dhcpd'][$interface]['staticmap'] as $entry) {
        if ($entry['ipaddr'] === $ipaddr) {
            return true;
        }
    }
    return false;
}

// Function to check if a MAC address already has a static assignment
function mac_exists($mac, $interface) {
    global $config;
    foreach ($config['dhcpd'][$interface]['staticmap'] as $entry) {
        if (strcasecmp($entry['mac'], $mac) == 0) { // Case-insensitive MAC comparison
            return true;
        }
    }
    return false;
}

// Function to check if a hostname already has a static assignment
function hostname_exists($hostname, $interface) {
    global $config;
    foreach ($config['dhcpd'][$interface]['staticmap'] as $entry) {
        if (!empty($entry['hostname']) && strcasecmp($entry['hostname'], $hostname) == 0) {
            return true;
        }
    }
    return false;
}

// Get interface subnets
$interface_subnets = [];
foreach ($config['dhcpd'] as $interface => $dhcp_config) {
    if (isset($config['interfaces'][$interface]['ipaddr']) && isset($config['interfaces'][$interface]['subnet'])) {
        $interface_subnets[$interface] = [
            'ip' => $config['interfaces'][$interface]['ipaddr'],
            'mask' => $config['interfaces'][$interface]['subnet']
        ];
    }
}

// Function to check if an IP falls within a DHCP range
function is_ip_in_range($ip, $range_start, $range_end) {
    $ip_long = ip2long($ip);
    return ($ip_long >= ip2long($range_start) && $ip_long <= ip2long($range_end));
}

// Return true when give IP address resides inside the given subnet/mask
function is_ip_in_subnet($ip, $subnet, $mask) {
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = ~((1 << (32 - $mask)) - 1); // Create subnet mask in long format

    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

// Function to find the correct interface for a given IP
function find_interface_for_ip($ip, $interface_subnets) {
    foreach ($interface_subnets as $interface => ['ip' => $subnet, 'mask' => $mask]) {
        if (is_ip_in_subnet($ip, $subnet, $mask)) {
            return $interface;
        }
    }
    return null;
}


// Read CSV file
$handle = fopen($csv_file, "r");
if (!$handle) {
    die("Error: Unable to open CSV file.\n");
}

// Skip header row
$header = fgetcsv($handle);

$count = 0;
$skipped = 0;

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    if (count($data) < 3) {
        echo "Skipping invalid row: " . implode(",", $data) . "\n";
        $skipped++;
        continue;
    }

    $mac = trim($data[0]);
    $ipaddr = trim($data[1]);
    $hostname = trim($data[2]);
    $description = isset($data[3]) ? trim($data[3]) : "";

    // Validate MAC and IP format
    if (!filter_var($ipaddr, FILTER_VALIDATE_IP) || !preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac)) {
        echo "Invalid MAC or IP, skipping: MAC=$mac IP=$ipaddr\n";
        $skipped++;
        continue;
    }

    // determine which interface to use for DHCP scope
    $interface = find_interface_for_ip($ipaddr, $interface_subnets);
    if (!$interface) {
        fwrite(STDERR, "Skipping: No matching interface found for IP $ipaddr\n");
        $skipped++;
        continue;
    }

    // Check if IP, MAC, or Hostname already exist
    if (ip_exists($ipaddr, $interface)) {
        echo "Skipping: IP address $ipaddr is already assigned.\n";
        $skipped++;
        continue;
    }
    if (mac_exists($mac, $interface)) {
        echo "Skipping: MAC address $mac is already assigned.\n";
        $skipped++;
        continue;
    }
    if (hostname_exists($hostname, $interface)) {
        echo "Skipping: Hostname $hostname is already assigned.\n";
        $skipped++;
        continue;
    }

    // Get the DHCP range for this interface
    $dhcp_range = $config['dhcpd'][$interface]['range'] ?? null;
    if ($dhcp_range && !$allow_in_range) {
        $range_start = $dhcp_range['from'];
        $range_end = $dhcp_range['to'];

        if (is_ip_in_range($ipaddr, $range_start, $range_end)) {
            echo "Skipping: IP $ipaddr falls within the DHCP range ($range_start - $range_end) and --allow was not provided.\n";
            $skipped++;
            continue;
        }
    }

    // Create static mapping entry
    $staticmap = array();
    $staticmap['mac'] = $mac;
    $staticmap['ipaddr'] = $ipaddr;
    $staticmap['hostname'] = $hostname;
    $staticmap['descr'] = $description;

    // Add entry to pfSense DHCP config
    $config['dhcpd'][$interface]['staticmap'][] = $staticmap;
    echo "Added: $ipaddr for MAC $mac on interface $interface\n";
    $count++;
}

fclose($handle);

// Save and apply changes if new reservations were added
if ($count > 0) {
    write_config("Added DHCP static mappings from CSV");
    services_dhcpd_configure(); // Reload DHCP service
    echo "Successfully added $count new DHCP reservations.\n";
}

if ($skipped > 0) {
    echo "Skipped $skipped entries due to duplicates or errors.\n";
}

?>
