#!/bin/bash

if [[ "$1" == "" || "$2" == "" ]]; then
    echo "error: You need to put paths of wordpress installation"
    exit
fi

SourceDir="$1"
DestinationDir="$2"

if [ ! -d "$SourceDir" ]; then
    echo "error: Source path either doesn't exists or it's not a directory"
    exit
elif [ ! -d "$DestinationDir" ]; then
    echo "error: Destination path either doesn't exists or it's not a directory"
    exit
fi

if [ ! -f "$SourceDir/wp-config.php" ]; then
   echo "error: file not found wp-config.php on Source path"
   exit
elif  [ ! -f "$DestinationDir/wp-config.php" ]; then
   echo "error: file not found wp-config.php on Destination path"
   exit
fi

# Get Source database params
S_DB_NAME=`cat $SourceDir/wp-config.php | grep DB_NAME | cut -d \' -f 4`
S_DB_USER=`cat $SourceDir/wp-config.php | grep DB_USER | cut -d \' -f 4`
S_DB_PASS=`cat $SourceDir/wp-config.php | grep DB_PASSWORD | cut -d \' -f 4`

# Get Destination database params
D_DB_NAME=`cat $DestinationDir/wp-config.php | grep DB_NAME | cut -d \' -f 4`
D_DB_USER=`cat $DestinationDir/wp-config.php | grep DB_USER | cut -d \' -f 4`
D_DB_PASS=`cat $DestinationDir/wp-config.php | grep DB_PASSWORD | cut -d \' -f 4`

php -f SaveDB.php s[]=$S_DB_NAME s[]=$S_DB_USER s[]=$S_DB_PASS s[]=$SourceDir d[]=$D_DB_NAME d[]=$D_DB_USER d[]=$D_DB_PASS d[]=$DestinationDir

if [ $? -eq 0 ]; then
    echo OK
else
    echo FAIL
fi