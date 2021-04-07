# Entur2MQTT
A very simple script to read real-time data from EnTur (Norwegian public transportation) and publish it to a local MQTT broker.

This script is based on Bluerhinos' project phpMQTT, which can be found here: https://github.com/bluerhinos/phpMQTT
The scriptfile from Bluerhinos, called phpMQTT.php needs to be installed in the same directory as Entur2MQTT.php in order for the solution to work.

Additional info you will be asked for on the first run (make sure to have this information handy):

- Quay or StopID to retrieve data from
    This can be found by visiting https://stoppested.entur.org (login guest/guest), and find either a quay ID, or a StopPlace ID. The difference between "quay" and "stopplace" is that the quay is a specific platform, in a specific direction for the stopplace. A stopplace can also encapsulate several other stopplaces in places where there are several different services in a limited geographic area.
- Interval between the runs of the script. You do not want the interval to be too long, since this is real-time data. However, the data returned is given in "minutes until next departure", so checking too often is not really required. Default value is 30 seconds.
- Text to use for "Now". When time to departure is "0", most public screens show the Norwegian word "Nå", meaning "Now". Here, you can choose which word to return for this specific use case.
- Number to return. Here, you choose how many departures you want to return. If you return departures from a large StopPlace, you probably want to return more data than if you only query a single quay in a housing area.
- Mosquitto host
- Mosquitto username and password
- Base topic to use when publishing Mosquitto data. The quay/stopplace ID will be added to the base topic, to make it easier to distinguish, in case you run multiple instances of the script to retrieve data from more than one stop place.

The script has been tested using PHP 7.x on Linux.

<b>Command line switches</b><br>
The script supports the following command line swithces:<br>
"-s" or "--single": Just query data once, and quit. Using this, sending commands to the appliance will not work.<br>
"-c" or "--create": Create new config file. If you already have an existing config file, default values for everything  will be retreived from the existing config file. Using this switch, no data is retreived.<br>
"-j" or "--json": Output the data as JSON. Some home automation systems prefer the data presented as json<br>
"-D" or "--debug": Output all debug information while the script is running. <br>
"-d" or "--dump": Dumps the data retreieved from Entur to verify that the data is correct.<br>


<b>Installation instructions</b><br>
This script has been developed using Ubuntu, and I have only tested it using this distribution. However, installation should be similar for all Debian-based distributions. For RedHat, I recommend Googling "how to install PHP" for your release. It should be easy, using yum.

In Ubuntu, install PHP and the required PHP librarys with the following command:<br>
 apt-get update && apt-get install -y php-cli php-common php-curl php-json php-readline <br>

Copy this script and phpMQTT.php into a folder where you have write permissions (for the config file). Gather your MQTT information, and run "php ./Entur2MQTT.php -c". The config-file has now been created, and you can run the script with whatever parameters you need from here.

