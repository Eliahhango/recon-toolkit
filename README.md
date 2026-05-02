# elitechwiz- - Educational Recon Toolkit

elitechwiz- is a PHP 8+ educational toolkit for learning web reconnaissance techniques, safe scanning workflows, and network behavior analysis. It keeps a menu-driven experience while offering fast subdomain discovery and a dedicated zero-rate host research mode.

Safety warning:
This tool is for educational and authorized security testing only. Do not use it against targets without explicit permission. High-speed scanning can violate terms of service or local laws.

## Key Features
- Menu-driven scans (basic recon, DNS/WHOIS, crawler, etc.)
- Enhanced Subdomain Scan mode (DNS + HTTP/HTTPS liveness checks)
- Zero Rate Host Scan mode (high-speed research mode)
- CSV/JSON/TXT output formats
- CLI support for automation
- Cross-platform: Windows, Linux, Termux

## Requirements
- PHP 8+
- PHP extensions: curl, dom (php-xml)

## Install
- git clone `https://https://github.com/Eliahhango/elitechwiz-elitechwiz-educational-recon-toolkitelitechwiz-educational-recon-toolkitelitechwiz-educational-recon-toolkit`
- cd elitechwiz-
- php elitechwiz.php

## Quick Start (Menu)
1) Run: `php elitechwiz.php`
2) Type a target domain (no http/https)
3) Choose a scan mode from the menu

## CLI Examples
Subdomain scan:
- `php elitechwiz.php --mode=subdomain --domain=example.com --wordlist=wordlists/elitechwiz_subdomains_default.txt --permutations=1 --protocols=http,https --threads=50 --format=csv --output=results/subdomains_example.csv`

Zero-rate scan:
- `php elitechwiz.php --mode=zero-rate --hosts=hosts.txt --protocols=http,https --method=HEAD --threads=100 --dns=1 --format=csv --output=results/zero_rate.csv --fail-log=results/zero_rate_failures.csv`

## Enhanced Subdomain Scan
- DNS resolution checks (A/AAAA)
- HTTP/HTTPS liveness checks (status codes)
- Title and Server header capture (when available)
- Built-in wordlist + permutation support
- Save results as CSV/JSON/TXT

## Zero Rate Host Scan (Educational)
This mode is designed to observe how different hosts respond on a given network. It can be used to research zero-rating behavior (e.g., carrier whitelisting). It performs high-speed checks with no artificial delays. Use only on authorized targets.

Captured fields:
- host, protocol, port
- status_code, response_time_ms, content_length
- server, title, redirect_url, notes

## Cross-Platform Notes (Windows / Linux / Termux / Ubuntu)
elitechwiz runs anywhere PHP 8+ is available.
- Windows: install PHP 8+, enable `curl` and `dom` in php.ini
- Ubuntu/Debian: `sudo apt-get install php-cli php-curl php-xml`
- Termux: `pkg install php php-xml`

The `fix` command detects your platform and shows the right instructions.

## Integrity Protection
The runtime verifies a signed manifest of core files. If these files are modified, the tool will refuse to run and display a warning. This is intended to keep releases consistent. If you want to customize the tool, fork the repository and rebuild your own release.

## Author
- Name: Eliah Hango
- Phone: +255688164510
- Portfolio: https://www.elitechwiz.site
- GitHub: https://github.com/Eliahhango

## License
MIT. The original license notice is retained in `LICENSE` as required.
# elitechwiz-
# elitechwiz-
