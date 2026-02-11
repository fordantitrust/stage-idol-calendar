@echo off
REM Quick Test Script for Windows
REM Run this before committing code

echo ========================================
echo      Quick Pre-Commit Test Suite
echo ========================================
echo.

REM Check PHP version
echo Checking PHP version...
php -v
echo.

REM Run security tests
echo Running security tests...
php tests/run-tests.php SecurityTest
if errorlevel 1 goto :error
echo.

REM Run cache tests
echo Running cache tests...
php tests/run-tests.php CacheTest
if errorlevel 1 goto :error
echo.

REM Run integration tests
echo Running integration tests...
php tests/run-tests.php IntegrationTest
if errorlevel 1 goto :error
echo.

echo Quick tests completed successfully!
echo.
echo To run full test suite:
echo   php tests/run-tests.php
echo.
goto :end

:error
echo.
echo Tests failed! Please fix errors before committing.
exit /b 1

:end
