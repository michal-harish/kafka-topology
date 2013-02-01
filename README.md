kafka-topology
==============

A simple web application that scans zookeeper and kafka for topics and which consumer groups consume them.
It also tries to detect whether consumer each is "active" by looking how far behind the latest offsets of the respective kafka partition it is. 

It is built on kafka-php library and also uses heavlity php-zookeeper extension.

It could also be an interface to create and delete topics.


