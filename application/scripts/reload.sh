#!/bin/bash

# Default Configuration
NM_USER='ntim'
PROJECT_DIR='/var/www/nominatim'

# Function to print usage
print_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo "Options:"
    echo "  -h, --help    Show this help message"
    echo
    echo "Example:"
    echo "  $0"
    exit 1
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            print_usage
            ;;
        *)
            echo "Unknown option: $1"
            print_usage
            ;;
    esac
    shift
done

# Main execution
main() {
    # Check if running as root
    if [ "$EUID" -ne 0 ]; then 
        echo "Please run as root"
        exit 1
    fi

    echo "Starting Nominatim reindexing process..."

    # Switch to Nominatim user and perform reindexing
    su - ${NM_USER} <<EOSSH
nominatim index --project-dir "${PROJECT_DIR}" --no-boundaries
EOSSH

    echo "Reindexing completed."
    echo "Your Nominatim instance has been updated."
}

main
