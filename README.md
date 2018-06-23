# php-alarmdotcom
A PHP library for reading sensor data from your Alarm.com account.


## Install

    git clone https://github.com/a15lam/php-alarmdotcom
    cd php-alarmdotcom
    composer install
    
## Configuration

1. Rename file .env-example to .env
2. Edit .env to enter your Alarm.com username and password
3. Run example/sensors.php to get all your sensor data

## Usage

    $alarm = new \a15lam\AlarmDotCom\AlarmDotCom();
    $sensors = $alarm->sensors();
    $door_sensor = $alarm->sensors('door-sensor-id');

