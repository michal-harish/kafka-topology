kafka-topology
==============

A simple web application scans zookeeper for topics and which consumer groups consum them and tries to detect also whether they are "active" by looking how far behind the latest offsets of the respective kafka partitions they are. It could also be an interface to create and delete topics.