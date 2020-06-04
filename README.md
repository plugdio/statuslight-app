# What is this?
The Statuslight Online service fetches your status information from different services and allows you to visualize it physically to others. You can either show your status on a phone’s screen or you could build your own device.

Building your own device is not difficult but requires some understanding of the MQTT protocol, microcontrollers, and other electronic components.

# How it works?
You login to Statuslight Online with your Office 365, Google or Slack account and you add your device(s). The service will then send updates to the device(s) as your status changes.

- For Teams we use the presence information (https://docs.microsoft.com/en-us/microsoftteams/presence-admins)
- For Google we use the free/busy information of your primary calendar (https://developers.google.com/calendar/v3/reference/freebusy)
- For Slack we use the status text; we set you busy if the status is ‘In a meeting’ (https://api.slack.com/docs/presence-and-status)

(It may happen that your company policy does not allow you to use external applications with your corporate account.)

# Protocol
The service works with any device that is capable to talk MQTT. It can be an ESP2866/ESP32, an Arduino or a Raspberry Pi. We have a reference project with ESP2866 (https://github.com/plugdio/statuslight-rgb-device)

The protocol follows the Homie IoT convention (https://homieiot.github.io/). There is a 'statuslight' node with 2 properties: status and statusdetail. The service publishes the information to these every minute.

Your device doesn’t need to use the Homie framework or implement the full convention, it’s enough to subscribe to these topics.

        SL / {your_device_id} / statuslight / status / set --> busy
        SL / {your_device_id} / statuslight / statusdetail / set --> Teams: Busy/In a meeting
      
You need to authenticate your device on MQTT: username can be anything (but you can’t change it later) and the password is the PIN number that is presented after you added a device.

The values for the status topic can be:

- free
- busy
- away
- offline
- unknown
- error

For statusdetail we use extra details from the presence provider.

# Build your own device
There is a reference implementation for the service: the light of the device will be green, red or yellow depending on your status. The source code can be found here: https://github.com/plugdio/statuslight-rgb-device

You need:

- LOLIN (WEMOS) D1 mini
- RGB LED Shield for LOLIN (WEMOS) D1 mini
- Soldering iron and tin
It’s recommended to use the repository with PlatformIO, but you can also use the Arduino IDE too. The code is based on the homie-esp8266 (https://github.com/homieiot/homie-esp8266) so we can use all the nice features from it, like the UI for configuration. (Don’t forget to upload the data folder to the device.)

After you have built the code and uploaded it to your device:

1. Turn on the device. It will create a new wifi network with the name SL-xxxxxxx.
2. Connect to that network and follow the steps in the configuration UI: select the wifi network that the device should use, enter the password for it, and enter your PIN number.
3. Then your device will reboot and it’s ready to be used.

# Questions?
All element of the service is available on GitHub.

- The Statuslight Online service deployable with docker-compose - https://github.com/plugdio/statuslight-app
- Reference implementation for a device - https://github.com/plugdio/statuslight-rgb-device
- The configuration UI for the device - https://github.com/plugdio/statuslight-rgb-device-ui

If you have questions, please submit an issue.
