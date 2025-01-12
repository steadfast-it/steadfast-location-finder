
# Steadfast Location Finder

This repository contains the Steadfast Location Finder application.

## Prerequisites

Before running the application, ensure you have the following installed:

- **Ubuntu (22.04)**
- **Git**

# Nominatim Server Installation (Skip if Already Installed)

Step 1: Get the Nominatim-Server.sh script from GitHub

      wget https://raw.githubusercontent.com/AcuGIS/Nominatim-Server/master/Nominatim-Server-22.sh

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

# Geo Location API Documentation

A RESTful API collection for managing location data using Nominatim. This API allows you to add new locations and search for existing locations in the database.

## Authentication

The API uses API key authentication. Include your API key in the request headers:

```
X-API-Key: your-api-key
```

## Base URL

Replace `your-server-url` with your actual server URL in the requests.

## Endpoints

### 1. Add Location

Add a new location to the Nominatim database.

- **URL**: `POST {{base_url}}`
- **Headers**:
  - `X-API-Key`: Your API key
  - `Accept`: application/json
  - `Content-Type`: application/json

#### Required Fields

```json
{
    "name": "string",
    "city": "string",
    "suburb": "string",
    "country": "string",
    "latitude": number,
    "longitude": number
}
```

#### Optional Fields

- **Basic Information**
  - `english_name`: English version of the location name
  - `website`: Location's website URL

- **Address Details**
  - `house_number`: Building number
  - `street`: Street name
  - `postcode`: Postal code
  - `state`: State/Province
  - `country_code`: Two-letter country code

- **Contact Information**
  - `phone`: Contact phone number
  - `email`: Contact email address

- **Facility Details**
  - `hours`: Operating hours
  - `wheelchair`: Wheelchair accessibility status
  - `floors`: Number of floors
  - `capacity`: Building capacity
  - `parking`: Parking availability
  - `internet`: Internet connectivity details

- **Organization Details**
  - `company`: Company name
  - `employees`: Number of employees

- **Management Information**
  - `creator`: Name of the person who added the location
  - `maintainer`: Name of the maintenance team/person
  - `maintainer_email`: Maintenance team's contact email

#### Complete Demo Request

Here's a full example showing all possible fields:

```json
{
    "name": "SteadSoft",
    "city": "Dhaka",
    "suburb": "Mohammadpur",
    "country": "Bangladesh",
    "latitude": 23.744431306905756,
    "longitude": 90.35132875476877,

    "english_name": "Hello World Ltd",
    "website": "https://example.com",
    
    "house_number": "123",
    "street": "Main Street",
    "postcode": "1207",
    "state": "Dhaka",
    "country_code": "BD",
    
    "phone": "+880123456789",
    "email": "contact@example.com",
    
    "hours": "Mo-Fr 09:00-17:00",
    "wheelchair": "yes",
    "floors": "3",
    "capacity": "500",
    
    "parking": "yes",
    "internet": "wlan - Google Internet, Password - google1234",
    
    "company": "Coo Gle",
    "employees": "50",
    
    "creator": "John Doe",
    "maintainer": "Facilities Team",
    "maintainer_email": "facilities@example.com"
}
```

#### Response

Success (200 OK):
```json
{
    "success": true,
    "message": "Location added successfully",
    "output": []
}
```

Error (400 Bad Request):
```json
{
    "error": "Missing required fields: name, city, suburb, country, latitude, longitude"
}
```

### 2. Search Location

Search for locations in the Nominatim database.

- **URL**: `GET {{base_url}}/nominatim/search.php`
- **Method**: GET

#### Query Parameters

- `q` (required): Search query string
- `format`: Response format (default: jsonv2)
- `addressdetails`: Include address details (0 or 1)
- `namedetails`: Include name details (0 or 1)
- `extratags`: Include extra tags (0 or 1)
- `polygon_geojson`: Include GeoJSON polygon (0 or 1)
- `limit`: Maximum number of results (default: 5)

#### Sample Response

```json
[
    {
        "place_id": 123456,
        "licence": "Data © OpenStreetMap contributors, ODbL 1.0",
        "osm_type": "node",
        "osm_id": 7654321,
        "boundingbox": ["23.7443", "23.7444", "90.3513", "90.3514"],
        "lat": "23.744431306905756",
        "lon": "90.35132875476877",
        "display_name": "Hello World, Mohammadpur, Dhaka, Bangladesh",
        "class": "office",
        "type": "software",
        "importance": 0.001
    }
]
```


## License

**Warning: This project is closed-source.**

This project is not open for public modification, redistribution, or contributions. Please use the provided features as per the terms of the license (if applicable). Unauthorized usage or distribution is prohibited.

If you have any questions or need access, please contact the repository owner.