#!/bin/bash
# Quick Test Script
# Run this before committing code

set -e  # Exit on error

echo "╔════════════════════════════════════════╗"
echo "║     Quick Pre-Commit Test Suite       ║"
echo "╚════════════════════════════════════════╝"
echo ""

# Check PHP version
echo "📌 Checking PHP version..."
php -v | head -n 1
echo ""

# Run security tests (fastest and most critical)
echo "🔒 Running security tests..."
php tests/run-tests.php SecurityTest
echo ""

# Run cache tests
echo "💾 Running cache tests..."
php tests/run-tests.php CacheTest
echo ""

# Run integration tests
echo "🔗 Running integration tests..."
php tests/run-tests.php IntegrationTest
echo ""

echo "✅ Quick tests completed successfully!"
echo ""
echo "💡 To run full test suite:"
echo "   php tests/run-tests.php"
echo ""
