
# Steadfast Location Finder

This repository contains the Steadfast Location Finder application.

## Prerequisites

Before running the application, ensure you have the following installed:

- **Ubuntu (22.04)**
- **Git**

# Nominatim Server Installation (Skip if Already Installed)

Step 1: Get the Nominatim-Server.sh script from GitHub

      wget https://raw.githubusercontent.com/AcuGIS/Nominatim-Server/master/Nominatim-Server.sh

Step 2: Make it executable:

    chmod 755 Nominatim-Server-22.sh

Step 3: Run the script

The script accepts a PBF url:

    ./Nominatim-Server-22.sh  https://download.geofabrik.de/asia/bangladesh-latest.osm.pbf


# Location API

Follow these steps to get the application up and running:

### 1. Clone the Repository

First, clone the repository to your local machine:

```bash
git clone https://github.com/steadfast-it/steadfast-location-finder.git
```

### 2. Navigate to the Application Directory

Change into the application's directory:

```bash
cd steadfast-location-finder
```

### 3. Grant Execute Permissions

Ensure that the `install.sh` script has execute permissions. Run the following command:

```bash
chmod +x install.sh
```

### 4. Run the Installation Script

After granting execute permissions, run the installation script to install all dependencies:

```bash
./install.sh
```

### 5. Set the API Key

1. Open the `/var/www/geo-location-api/index.php` file and replace the placeholder API key `XXXX` with your actual API key.

```php
define('API_KEY', 'YOUR_API_KEY');
```

### 6: Authentication

To authenticate your API requests, include the `X-API-Key` header with your valid API key.

## License

**Warning: This project is closed-source.**

This project is not open for public modification, redistribution, or contributions. Please use the provided features as per the terms of the license (if applicable). Unauthorized usage or distribution is prohibited.

If you have any questions or need access, please contact the repository owner.