#!/usr/bin/python3
import configparser
import os
import sys

ip = sys.argv[1]

conf = configparser.ConfigParser()
conf.optionxform = str
conf.read("targetRabbitMQ.ini")

conf.set("testServer", "BROKER_HOST", ip)
with open('targetRabbitMQ.ini', 'w+') as configfile:
    conf.write(configfile)


#ip = conf.get("testServer", "BROKER_HOST")

