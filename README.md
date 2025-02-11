# pfSense DHCP Static Mapping Scripts

## Overview
These scripts are designed to manage static DHCP mappings on a pfSense firewall running **pfSense 2.7.2 CE**.

- `add_dhcp_static.php`: Adds new DHCP static assignments from a CSV file.
- `export_dhcp_static.php`: Exports existing DHCP static assignments to a CSV file.
- `remove_dhcp_static.php`: Removes specific static DHCP assignments based on IP, MAC, or hostname.
- `remove_all_dhcp_static.php`: Removes all static DHCP assignments from all interfaces.

## Prerequisites
- These scripts must be run **directly on the pfSense firewall**.
- Requires root access (`ssh` or console access).
- Basic understanding of pfSense DHCP configurations.

---

## `add_dhcp_static.php`

### Description
* This script reads a CSV file containing DHCP static assignments and adds them to the pfSense DHCP server.
* It is capable of **properly adding** static entries into different DHCP interface scopes.
* This is useful when moving a group of DHCP static assignments from one VLAN to another because this can't be done from the Web GUI.
* This can override the limitation within the Web GUI which does not allow you to create a static assignment within the reservation pool.
* * Be aware that you will not be able to then edit this entry from within the Web GUI because it will error out because it does not allow for these types of entries.

### CSV Format
The CSV file should have the following header and format:
```
mac,ipaddr,hostname,description
00:11:22:33:44:55,192.168.1.100,device1,Test Device
AA:BB:CC:DD:EE:FF,192.168.1.101,device2,Another Test Device
```

### Handling Duplicates
Before adding a new entry, the script checks if:
- The **IP address** already has a static assignment.
- The **MAC address** is already assigned.
- The **hostname** is already in use.

If any of these exist, the script will **skip** the entry to prevent conflicts.

### Usage
```sh
php /root/add_dhcp_static.php /path/to/input.csv
```

---

## `export_dhcp_static.php`

### Description
This script exports all existing DHCP static assignments, including those on VLANs, to a CSV format.

### Output Format
The script prints output in the following format:
```
mac,ipaddr,hostname,description
00:11:22:33:44:55,192.168.1.100,device1,Test Device
AA:BB:CC:DD:EE:FF,192.168.1.101,device2,Another Test Device
```

### Usage
To export DHCP reservations and save them to a file:
```sh
php /root/export_dhcp_static.php > dhcp_static_reservations.csv
```
To display directly in the terminal:
```sh
php /root/export_dhcp_static.php
```

---

## `remove_dhcp_static.php`

### Description
This script removes specific static DHCP assignments based on IP address, MAC address, or hostname.

### Usage
```sh
php /root/remove_dhcp_static.php <IP/MAC/Hostname> [<IP/MAC/Hostname> ...]
```

**Example:**
```sh
php /root/remove_dhcp_static.php 192.168.1.100 00:11:22:33:44:55 device1
```

**Expected Output:**
```
Removed static DHCP reservation for MAC 00:11:22:33:44:55
Removed static DHCP reservation for IP 192.168.1.100
Removed static DHCP reservation for hostname device1
Changes applied.
```

---

## `remove_all_dhcp_static.php`

### Description
This script removes **all** static DHCP assignments from all interfaces on the pfSense firewall and applies the changes.

### Usage
```sh
php /root/remove_all_dhcp_static.php
```

**Expected Output:**
```
Removed all static DHCP reservations from interface: lan
Removed all static DHCP reservations from interface: vlan10
Removed all static DHCP reservations from interface: vlan20
All static DHCP reservations have been removed and changes applied.
```

---

## Example Demo
### Adding DHCP Reservations
```sh
php /root/add_dhcp_static.php new_reservations.csv
```
**Expected Output:**
```
Processing CSV file: new_reservations.csv
Skipping: MAC 00:11:22:33:44:55 already assigned
Added: 192.168.1.102 for MAC FF:EE:DD:CC:BB:AA
Applying changes...
Done.
```

### Exporting DHCP Reservations
```sh
php /root/export_dhcp_static.php > dhcp_backup.csv
```
**Expected Output (inside `dhcp_backup.csv`):**
```
mac,ipaddr,hostname,description
00:11:22:33:44:55,192.168.1.100,device1,Test Device
AA:BB:CC:DD:EE:FF,192.168.1.101,device2,Another Test Device
```

### Removing Specific DHCP Reservations
```sh
php /root/remove_dhcp_static.php 192.168.1.100 00:11:22:33:44:55 device1
```
**Expected Output:**
```
Removed static DHCP reservation for MAC 00:11:22:33:44:55
Removed static DHCP reservation for IP 192.168.1.100
Removed static DHCP reservation for hostname device1
Changes applied.
```

### Removing All DHCP Reservations
```sh
php /root/remove_all_dhcp_static.php
```
**Expected Output:**
```
Removed all static DHCP reservations from interface: lan
Removed all static DHCP reservations from interface: vlan10
Removed all static DHCP reservations from interface: vlan20
All static DHCP reservations have been removed and changes applied.
```

---

## Notes
- These scripts modify the **pfSense configuration**, so always **backup your settings** before running them.
- After making changes with `add_dhcp_static.php`, `remove_dhcp_static.php`, or `remove_all_dhcp_static.php`, you may need to restart the DHCP service for them to take effect.

