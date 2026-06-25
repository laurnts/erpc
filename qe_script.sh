#!/bin/bash

# Script to approve Quotation Evaluation (QE), Profit & Loss (PNL), or Supplier Order (PO) documents
# Usage: sh qe_script.sh [qe_number|pnl_number|po_number]
# Example: sh qe_script.sh 010-DS/QE/II/2026
# Example: sh qe_script.sh 0010/EL-PNL/II/2026
# Example: sh qe_script.sh PO-2026-0012

# Check if arguments are provided
if [ $# -lt 1 ]; then
    echo "Error: Missing arguments"
    echo "Usage: sh qe_script.sh [qe_number|pnl_number|po_number]"
    echo "Example: sh qe_script.sh 010-DS/QE/II/2026"
    echo "Example: sh qe_script.sh 0010/EL-PNL/II/2026"
    echo "Example: sh qe_script.sh PO-2026-0012"
    exit 1
fi

NUMBER=$1

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the project directory
cd "$SCRIPT_DIR" || exit 1

# Route to the correct artisan command based on document number format
case "$NUMBER" in
    PO-*|PO\ *|po-*|po\ *)
        php artisan supplier-order:approve "$NUMBER"
        ;;
    *)
        php artisan approve:qe-or-pnl "$NUMBER"
        ;;
esac

# Exit with the same code as the artisan command
exit $?
