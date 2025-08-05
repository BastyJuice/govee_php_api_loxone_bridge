
# Govee PHP API Integration with Loxone Support

This PHP-based REST API allows control of Govee smart lights using the official Govee Cloud API. It supports RGB, brightness, scenes, temperature, and group actions like "all on" or "all off". The script is designed to integrate smoothly with Loxone, DMX, and other smart home systems.

## Features

💡 Turn devices on/off (individually or all at once)  
🎚️ Set brightness (0–100%)  
🎨 Set RGB color using `rgb=...`, `colorInt=...`, or Loxone-style integers (e.g. `20040060`)  
🌡️ Set color temperature (2000–9000 K)  
🎭 Activate predefined Govee scenes  
🔍 Query current status of a specific device  
📜 List all available Govee devices  
🛡️ Block external access (local-only mode) for security  
🧠 Automatic detection of Loxone RGB encoding with proper rounding logic  

## Requirements

✅ PHP 7.4 or higher  
🌐 Internet access (to connect to the Govee Cloud API)

## Installation

📂 Copy the `govee-bridge.php` file to your web server  
🔑 Set your Govee API Key in the configuration section  
🛡️ Enable or disable remote execution with `$RemoteExecute`  
⚙️ Set `$ColorIntIsBGR = true` if your input format uses BGR instead of RGB  

## Usage

Turn a single device on:

```
https://yourserver/govee-bridge.php?mac=XX:XX:XX...&model=H605C&turn=on
```

Set brightness:

```
https://yourserver/govee-bridge.php?mac=...&model=...&brightness=80
```

Set color with RGB:

```
https://yourserver/govee-bridge.php?mac=...&model=...&rgb=255,100,0
```

Set color using Loxone-style integer:

```
https://yourserver/govee-bridge.php?mac=...&model=...&colorInt=20040060
```

Turn all devices off:

```
https://yourserver/govee-bridge.php?alloff=1
```

Turn all devices on:

```
https://yourserver/govee-bridge.php?allon=1
```

List all devices:

```
https://yourserver/govee-bridge.php?devices=1
```

Get status of a specific device:

```
https://yourserver/govee-bridge.php?mac=...&model=...&status=1
```

## Loxone Integration

This API can be used in Loxone through Virtual HTTP Inputs and Outputs or the `HTTP` command. Perfect for creating smart lighting control directly in the Loxone Config software.

## Security Notes

To prevent external access, set:

```php
$RemoteExecute = false;
```

Only requests from private IP ranges (e.g., 192.168.x.x, 10.x.x.x) will be allowed.

## License

This project is licensed under the MIT License.

## Donation

If this project helps you, you can buy me a coffee:

[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://paypal.me/bastyjuice)
