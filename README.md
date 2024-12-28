# WebAlert Agent
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=sysadm-webalert_webalert-backend&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=sysadm-webalert_webalert-backend)
## Overview
WebAlert is a smoothie app to monitoring your sites, with real-time alerts and detailed metrics, you'll always stay informed about the status of your sites. 

## Features
- **Website Status Monitoring**: Keep track of your site's uptime and downtime in real-time.
- **Performance Metrics**:  Monitor response times and gather critical performance data to ensure your site is running smoothly.
- **Custom Alerts**: Get notified instantly about outages or performance issues with customizable alert settings.
- **Multi-Organization Support**: Manage multiple organizations, each with their own independent site configurations and metrics.
- **Smoothly Interface**: Enjoy a sleek, responsive UI built with Vue 3 and Bootstrap for real-time log visualization and management.

## Installation

### Prerequisites
- Symfony 7 (PHP 8.2)
- Symfony cli

### Local Build
1. Install required packages
   ```bash
   composer install
   ```
2. Run dev server
   ```bash
   symfony server:start
   ```

### Docker Build
1. Run docker compose dev
   ```sh
   docker compose --profile dev build
   docker compose --profile dev up -d
   ```
2. During the build of the image, you must set the following argument with your dns or * for dev.
   ```bash
   ACCESS_CONTROL_ALLOW_ORIGIN: "https://app.webalert.digital"
   ```

## Configuration
1. Create your .env file (only for local)
   ```bash
   wget https://github.com/symfony/demo/blob/main/.env
   ```
2. Set the following env variables as env variables.
   ```sh
   DATABASE_URL: "mysql://db_user:db_password@db_host:3306/your_database?serverVersion=8.0.32&charset=utf8mb4"
   FRONTEND_BASE_URL: "http://frontned_uri"
   PUPPETEER_CONTAINER_NAME: "http://puppeteer_dns_host:3000"
   MAILER_DSN: "smtp://smtp_user%40smtp_domain:smtp_password@smtp_host:587"
   EMAILER_FROM_EMAIL: "smtp_user@smtp_domain"
   ```
3. Create the keypairs for lexik
   ```sh
   php bin/console lexik:jwt:generate-keypair
   ```

## Contributing
We welcome contributions! Please follow these steps:
1. Fork the repository.
2. Create a feature branch.
3. Commit your changes.
4. Open a pull request.

## License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support
For issues or feature requests, please open an issue in the [GitHub repository](https://github.com/sysadm-webalert/webalert-backend/issues).

---
**WebAlert Agent** © 2024