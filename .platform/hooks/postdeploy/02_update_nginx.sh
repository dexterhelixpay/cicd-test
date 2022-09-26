#!/bin/bash

sudo amazon-linux-extras disable nginx1
sudo amazon-linux-extras enable epel

sudo yum clean metadata
sudo yum install epel-release -y
sudo yum install nginx -y

sudo service nginx restart
