#!/bin/bash

# Default Configuration
NM_USER='ntim'
PROJECT_DIR='/var/www/nominatim'

# Function to print usage
print_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo "Required Options:"
    echo "  -n, --name         Location name"
    echo "  -c, --city         City name"
    echo "  -s, --suburb       Suburb name"
    echo "  -co, --country     Country name"
    echo "  -lat, --latitude   Latitude"
    echo "  -lon, --longitude  Longitude"
    echo
    echo "Optional Options:"
    echo "  --english-name     Name in English"
    echo "  --website          Website URL"
    echo "  --house-number     House number"
    echo "  --street           Street name"
    echo "  --postcode         Postal code"
    echo "  --state           State/Province"
    echo "  --country-code     2-letter country code"
    echo "  --phone            Phone number"
    echo "  --email            Email address"
    echo "  --hours            Opening hours"
    echo "  --wheelchair       Wheelchair accessibility (yes/no/limited)"
    echo "  --floors           Number of building floors"
    echo "  --capacity         Maximum capacity"
    echo "  --parking          Parking availability (yes/no)"
    echo "  --internet         Internet access type"
    echo "  --company          Company name"
    echo "  --employees        Number of employees"
    echo "  --creator          Creator name"
    echo "  --maintainer      Maintainer name"
    echo "  --maintainer-email Maintainer email"
    echo "  -h, --help         Show this help message"
    echo
    echo "Example:"
    echo "  $0 -n 'Zavi Soft' -c 'Dhaka' -s 'Mohammadpur' -co 'Bangladesh' -lat 23.744431306905756 -lon 90.35132875476877 --website 'https://example.com' --phone '+1234567890'"
    exit 1
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -n|--name)
            LOCATION_NAME="$2"
            shift 2
            ;;
        -c|--city)
            CITY="$2"
            shift 2
            ;;
        -s|--suburb)
            SUBURB="$2"
            shift 2
            ;;
        -co|--country)
            COUNTRY="$2"
            shift 2
            ;;
        -lat|--latitude)
            LAT="$2"
            shift 2
            ;;
        -lon|--longitude)
            LON="$2"
            shift 2
            ;;
        --english-name)
            ENGLISH_NAME="$2"
            shift 2
            ;;
        --website)
            WEBSITE_URL="$2"
            shift 2
            ;;
        --house-number)
            HOUSE_NUMBER="$2"
            shift 2
            ;;
        --street)
            STREET="$2"
            shift 2
            ;;
        --postcode)
            POSTCODE="$2"
            shift 2
            ;;
        --state)
            STATE="$2"
            shift 2
            ;;
        --country-code)
            COUNTRY_CODE="$2"
            shift 2
            ;;
        --phone)
            PHONE="$2"
            shift 2
            ;;
        --email)
            EMAIL="$2"
            shift 2
            ;;
        --hours)
            OPENING_HOURS="$2"
            shift 2
            ;;
        --wheelchair)
            WHEELCHAIR_ACCESS="$2"
            shift 2
            ;;
        --floors)
            NUM_FLOORS="$2"
            shift 2
            ;;
        --capacity)
            MAX_CAPACITY="$2"
            shift 2
            ;;
        --parking)
            PARKING_AVAILABLE="$2"
            shift 2
            ;;
        --internet)
            INTERNET_ACCESS="$2"
            shift 2
            ;;
        --company)
            COMPANY_NAME="$2"
            shift 2
            ;;
        --employees)
            NUM_EMPLOYEES="$2"
            shift 2
            ;;
        --creator)
            CREATOR_NAME="$2"
            shift 2
            ;;
        --maintainer)
            MAINTAINER="$2"
            shift 2
            ;;
        --maintainer-email)
            MAINTAINER_EMAIL="$2"
            shift 2
            ;;
        -h|--help)
            print_usage
            ;;
        *)
            echo "Unknown option: $1"
            print_usage
            ;;
    esac
done

# Validate required parameters
validate_params() {
    local missing_params=()
    
    [[ -z "$LOCATION_NAME" ]] && missing_params+=("location name (-n)")
    [[ -z "$CITY" ]] && missing_params+=("city (-c)")
    [[ -z "$SUBURB" ]] && missing_params+=("suburb (-s)")
    [[ -z "$COUNTRY" ]] && missing_params+=("country (-co)")
    [[ -z "$LAT" ]] && missing_params+=("latitude (-lat)")
    [[ -z "$LON" ]] && missing_params+=("longitude (-lon)")
    
    if [[ ${#missing_params[@]} -ne 0 ]]; then
        echo "Error: Missing required parameters:"
        printf '%s\n' "${missing_params[@]}"
        print_usage
    fi
    
    # Validate latitude and longitude format
    if ! [[ "$LAT" =~ ^-?[0-9]+\.?[0-9]*$ ]]; then
        echo "Error: Invalid latitude format"
        exit 1
    fi
    if ! [[ "$LON" =~ ^-?[0-9]+\.?[0-9]*$ ]]; then
        echo "Error: Invalid longitude format"
        exit 1
    fi
}

# Generate a unique ID
UNIQUE_ID=$((RANDOM * 1))

# Function to add a tag if the value is not empty
add_tag() {
    local key="$1"
    local value="$2"
    if [[ -n "$value" ]]; then
        echo "    <tag k=\"$key\" v=\"$value\"/>"
    fi
}

# Create temporary OSM file
create_osm_file() {
    echo "Creating OSM file with location data..."
    {
        echo '<?xml version="1.0" encoding="UTF-8"?>'
        echo '<osm version="0.6" generator="Custom Location Script">'
        echo
        echo "  <node id=\"${UNIQUE_ID}\" lat=\"${LAT}\" lon=\"${LON}\" version=\"1\">"
        
        # Required tags
        add_tag "name" "${LOCATION_NAME}"
        add_tag "office" "software"
        add_tag "addr:city" "${CITY}"
        add_tag "addr:suburb" "${SUBURB}"
        add_tag "addr:country" "${COUNTRY}"
        
        # Optional tags - Basic Information
        add_tag "name:en" "${ENGLISH_NAME}"
        add_tag "website" "${WEBSITE_URL}"
        
        # Optional tags - Address Information
        add_tag "addr:housenumber" "${HOUSE_NUMBER}"
        add_tag "addr:street" "${STREET}"
        add_tag "addr:postcode" "${POSTCODE}"
        add_tag "addr:state" "${STATE}"
        add_tag "addr:country_code" "${COUNTRY_CODE}"
        
        # Optional tags - Contact Information
        add_tag "phone" "${PHONE}"
        add_tag "email" "${EMAIL}"
        
        # Optional tags - Additional Metadata
        add_tag "opening_hours" "${OPENING_HOURS}"
        add_tag "wheelchair" "${WHEELCHAIR_ACCESS}"
        [[ -n "${NUM_FLOORS}" ]] && add_tag "building" "yes"
        add_tag "building:levels" "${NUM_FLOORS}"
        add_tag "capacity" "${MAX_CAPACITY}"
        
        # Optional tags - Amenities
        add_tag "amenity:parking" "${PARKING_AVAILABLE}"
        add_tag "internet_access" "${INTERNET_ACCESS}"
        
        # Optional tags - Company Specific
        add_tag "company" "${COMPANY_NAME}"
        add_tag "employees" "${NUM_EMPLOYEES}"
        [[ -n "${COMPANY_NAME}" ]] && add_tag "industry" "software_development"
        
        # Optional tags - Tracking Information
        add_tag "created_by" "${CREATOR_NAME}"
        add_tag "maintainer" "${MAINTAINER}"
        add_tag "maintainer:email" "${MAINTAINER_EMAIL}"
        
        echo "  </node>"
        echo "</osm>"
    } > /tmp/custom_location.osm
}

# Import the custom location
import_location() {
    echo "Importing custom location into Nominatim..."
    nominatim add-data --file /tmp/custom_location.osm --project-dir "${PROJECT_DIR}"
}

# Update search index
update_index() {
    echo "Reindexing Nominatim data..."
    nominatim index --project-dir "${PROJECT_DIR}" --no-boundaries
}

# Main execution
main() {
    # Validate parameters first
    validate_params
    
    # Check if running as root
    if [ "$EUID" -ne 0 ]; then 
        echo "Please run as root"
        exit 1
    fi

    echo "Starting location addition process..."
    
    # Create OSM file
    create_osm_file
    
    # Switch to nominatim user and perform operations
    su - ${NM_USER} <<EOSSH
$(declare -f import_location)
$(declare -f update_index)
import_location
update_index
EOSSH
    
    # Cleanup
    rm -f /tmp/custom_location.osm
    
    echo "Location addition completed."
    echo "You can now search for '${LOCATION_NAME}' in your Nominatim instance."
    echo "Note: It may take a few minutes for the new location to be searchable."
}

main