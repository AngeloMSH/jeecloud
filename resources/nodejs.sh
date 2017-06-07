#!/bin/bash
cd $1
touch /tmp/jeecloud_dep
echo "Starting Install"

echo 0 > /tmp/jeecloud_dep
DIRECTORY="/var/www"
if [ ! -d "$DIRECTORY" ]; then
  echo "Creating home www-data for npm"
  sudo mkdir $DIRECTORY
fi
sudo chown -R www-data $DIRECTORY
echo 10 > /tmp/jeecloud_dep
actual=`nodejs -v`;
echo "Actual Version : ${actual}"

if [[ $actual == *"4."* || $actual == *"5."* ]]
then
  echo "Ok, this version works";
else
  echo "KO, need upgrade";
  echo "Deleting existing Nodejs and replace with recommanded package"
  sudo apt-get -y --purge autoremove nodejs npm
  arch=`arch`;
  echo 30 > /tmp/jeecloud_dep
  if [[ $arch == "armv6l" ]]
  then
    echo "Raspberry 1 detected, using armv6 package"
    sudo rm /etc/apt/sources.list.d/nodesource.list
    wget http://node-arm.herokuapp.com/node_latest_armhf.deb
    sudo dpkg -i node_latest_armhf.deb
    sudo ln -s /usr/local/bin/node /usr/local/bin/nodejs
    rm node_latest_armhf.deb
  fi

  if [[ $arch == "aarch64" ]]
  then
    wget http://dietpi.com/downloads/binaries/c2/nodejs_5-1_arm64.deb
    sudo dpkg -i nodejs_5-1_arm64.deb
    sudo ln -s /usr/local/bin/node /usr/local/bin/nodejs
    rm nodejs_5-1_arm64.deb
  fi

  if [[ $arch != "aarch64" && $arch != "armv6l" ]]
  then
    echo "Using official source"
    curl -sL https://deb.nodesource.com/setup_5.x | sudo -E bash -
    sudo apt-get install -y nodejs
  fi
  
  new=`nodejs -v`;
  echo "Version actuelle : ${new}"
fi

echo 65 > /tmp/jeecloud_dep

# installing modules for nodejs
cd ../node/
npm cache clean
sudo npm cache clean
sudo rm -rf node_modules
echo 70 > /tmp/jeecloud_dep

sudo npm install --unsafe-perm request
echo 75 > /tmp/jeecloud_dep

sudo npm install --unsafe-perm http
echo 80 > /tmp/jeecloud_dep

sudo npm install --unsafe-perm ws
echo 90 > /tmp/jeecloud_dep

sudo chown -R www-data *

rm /tmp/jeecloud_dep

echo "End of install"
