<?php
require_once("config.inc");
require_once("functions.inc");
require_once("util.inc");
require_once("interfaces.inc");
require_once("services.inc"); // Required for services_dhcpd_configure()

// Ensure a CSV file path is provided as an argument
if ($argc < 2) {
    die("Usage: php add_dhcp_static.php <path_to_csv_file>\n");
}

$csv_file = $argv[1]; // Get file path from command-line argument

// Check if file exists and is readable
if (!file_exists($csv_file) || !is_readable($csv_file)) {
    die("Error: CSV file not found or not readable at $csv_file\n");
}

// Function to check if an IP address already has a static assignment
function ip_exists($ipaddr) {
    global $config;
    foreach ($config['dhcpd']['lan']['staticmap'] as $entry) {
        if ($entry['ipaddr'] === $ipaddr) {
            return true;
        }
    }
    return false;
}

// Function to check if a MAC address already has a static assignment
function mac_exists($mac) {
    global $config;
    foreach ($config['dhcpd']['lan']['staticmap'] as $entry) {
        if (strcasecmp($entry['mac'], $mac) == 0) { // Case-insensitive MAC comparison
            return true;
        }
    }
    return false;
}

// Function to check if a hostname already has a static assignment
function hostname_exists($hostname) {
    global $config;
    foreach ($config['dhcpd']['lan']['staticmap'] as $entry) {
        if (!empty($entry['hostname']) && strcasecmp($entry['hostname'], $hostname) == 0) {
            return true;
        }
    }
    return false;
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

    // Check if IP, MAC, or Hostname already exist
    if (ip_exists($ipaddr)) {
        echo "Skipping: IP address $ipaddr is already assigned.\n";
        $skipped++;
        continue;
    }
    if (mac_exists($mac)) {
        echo "Skipping: MAC address $mac is already assigned.\n";
        $skipped++;
        continue;
    }
    if (hostname_exists($hostname)) {
        echo "Skipping: Hostname $hostname is already assigned.\n";
        $skipped++;
        continue;
    }

    // Create static mapping entry
    $staticmap = array();
    $staticmap['mac'] = $mac;
    $staticmap['ipaddr'] = $ipaddr;
    $staticmap['hostname'] = $hostname;
    $staticmap['descr'] = $description;

    // Add entry to pfSense DHCP config
    $config['dhcpd']['lan']['staticmap'][] = $staticmap;
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
