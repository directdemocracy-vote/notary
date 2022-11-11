# Publisher for *directdemocracy.vote* 

This repository contains a reference implementation of a publisher service for *directdemocracy.vote*.
Such an implementation is fully functional.
It aims at providing a simple working example for developers who would like to implement their own publisher service.
However, it has a number of limitations.

## Limitations

This publisher relies on a standard MySQL database which may not scale well with the number of users.
Moreover a standard database may not be the best way to store directdemocracy data.
A blockchain distributed ledger may be more appropriate but more complex to implement.
However, this sample implementation aims at providing a simple reference code.

## Scalable Implementations

Developers and organizations are encouraged to implement their own publishers using more scalable and more robust technologies to handle a very large number of users.
