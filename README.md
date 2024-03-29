# Notary for *directdemocracy.vote* 

This repository contains a reference implementation of a notary service for *directdemocracy.vote*.
It is live at https://notary.directdemocracy.vote.
Such an implementation is fully functional.
It aims at providing a simple working example for developers who would like to implement their own notary service.
However, it has a number of limitations.

## Limitations

This notary relies on a standard MySQL database running on a single server which may not scale well with the number of users.
Moreover a single standard database may not be the best way to store directdemocracy data.
A distributed data base may be more appropriate but more complex to implement.
However, this sample implementation aims at providing a simple reference code.

## Scalable Implementations

Developers and organizations are encouraged to implement their own notaries using more scalable and more robust technologies to handle a very large number of users and provide a better resilience of the service.

## Installation

### Requirements

You will need a simple web server running a recent version of PHP and MySQL.

### Dependencies

PHP dependencies should be installed by running `composer install` at the root of this repository.
They include [json-schema](https://github.com/opis/json-schema) to check the syntax of publications.
