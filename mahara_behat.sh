#!/bin/bash

function is_selenium_running {
    res=$(curl -o /dev/null --silent --write-out '%{http_code}\n' http://localhost:4444/wd/hub/status)
    if [[ $res == "200" ]]; then
        return 0;
    else
        return 1;
    fi
}

# Check we are not running as root for some weird reason
if [[ "$USER" = "root" ]]
then
    echo "This script should not be run as root"
    exit 1
fi

# Get action and Mahara dir
ACTION=$1
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd $DIR

if [ "$ACTION" = "action" ]
then

    # Wrap the util.php script

    PERFORM=$2
    php test/behat/scripts/util.php --$PERFORM

elif [ "$ACTION" = "run" ]
then

    # Run the Behat tests themselves (after any intial setup)
    TAGS=$2

    if is_selenium_running; then
        echo "Selenium is running"
    else
        echo "Start Selenium..."

        SELENIUM_VERSION_MAJOR=2.43
        SELENIUM_VERSION_MINOR=1

        SELENIUM_FILENAME=selenium-server-standalone-$SELENIUM_VERSION_MAJOR.$SELENIUM_VERSION_MINOR.jar
        SELENIUM_PATH=test/behat/$SELENIUM_FILENAME

        # If no Selenium installed, download it
        if [ ! -f $SELENIUM_PATH ]; then
            echo "Downloading Selenium..."
            wget -q -O $SELENIUM_PATH http://selenium-release.storage.googleapis.com/$SELENIUM_VERSION_MAJOR/$SELENIUM_FILENAME
            echo "Downloaded"
        fi

        java -jar $SELENIUM_PATH &>/dev/null &
        sleep 5

        if is_selenium_running; then
            echo "Selenium started"
        else
            echo "Selenium can't be started"
            exit 1
        fi
    fi

    echo "Start PHP server"
    php --server 127.0.0.1:8000 --docroot htdocs/ &>/dev/null &
    SERVER=$!

    echo "Enable test site"
    php test/behat/scripts/util.php --enable

    echo "Run Behat..."

    if [ "$TAGS" ]
    then
        echo "Only run tests with the tag: $TAGS"
    else
        echo "Run all tests"
    fi

    echo
    echo "=================================================="
    echo

    cd $DIR/test/behat
    if [ "$TAGS" ]
    then
        bin/behat --ansi --tags $TAGS
    else
        bin/behat --ansi
    fi

    cd $DIR

    echo
    echo "=================================================="
    echo
    echo "Shutdown"
    php test/behat/scripts/util.php --disable
    kill $SERVER
else
    # Help text if we got an unexpected (or empty) first param
    echo "Expected something like one of the following:"
    echo
    echo "# Run all tests:"
    echo "mahara_behat run"
    echo ""
    echo "# Run tests with specific tag:"
    echo "mahara_behat run @tagname"
    echo ""
    echo "# Enable test site:"
    echo "mahara_behat action enable"
    echo ""
    echo "# Disable test site:"
    echo "mahara_behat action disable"
    echo ""
    echo "# List other actions you can perform:"
    echo "mahara_behat action help"
    exit 1
fi
