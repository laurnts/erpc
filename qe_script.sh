#!/bin/bash

# Script to approve Quotation Evaluation (QE) or Profit & Loss (PNL) documents
# Usage: sh qe_script.sh [qe_number|pnl_number] [name_approver]
# Example: sh qe_script.sh 010-DS/QE/II/2026 Sabrina
# Example: sh qe_script.sh 0010/EL-PNL/II/2026 Sabrina

# Check if arguments are provided
if [ $# -lt 2 ]; then
    echo "Error: Missing arguments"
    echo "Usage: sh qe_script.sh [qe_number|pnl_number] [name_approver]"
    echo "Example: sh qe_script.sh 010-DS/QE/II/2026 Sabrina"
    echo "Example: sh qe_script.sh 0010/EL-PNL/II/2026 Sabrina"
    exit 1
fi

NUMBER=$1
APPROVER=$2

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the project directory
cd "$SCRIPT_DIR" || exit 1

# Run the Laravel artisan command
# Quote arguments to handle special characters in QE/PNL numbers
php artisan approve:qe-or-pnl "$NUMBER" "$APPROVER"

# Exit with the same code as the artisan command
exit $?
