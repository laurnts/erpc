#!/bin/bash

# Script to approve Quotation Evaluation (QE) or Profit & Loss (PNL) documents
# Usage: sh qe_script.sh [qe_number|pnl_number]
# Example: sh qe_script.sh 010-DS/QE/II/2026
# Example: sh qe_script.sh 0010/EL-PNL/II/2026

# Check if arguments are provided
if [ $# -lt 1 ]; then
    echo "Error: Missing arguments"
    echo "Usage: sh qe_script.sh [qe_number|pnl_number]"
    echo "Example: sh qe_script.sh 010-DS/QE/II/2026"
    echo "Example: sh qe_script.sh 0010/EL-PNL/II/2026"
    exit 1
fi

NUMBER=$1

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the project directory
cd "$SCRIPT_DIR" || exit 1

# Run the Laravel artisan command
# Quote arguments to handle special characters in QE/PNL numbers
php artisan approve:qe-or-pnl "$NUMBER"

# Exit with the same code as the artisan command
exit $?
