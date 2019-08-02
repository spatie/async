# Changelog

All notable changes to `async` will be documented in this file

## 1.0.4 - 2019-08-02

- Fix for `SynchronousProcess::resolveErrorOutput` (#73)

## 1.0.3 - 2019-07-22

- Fix for Symfony Process argument deprecation

## 1.0.1 - 2019-05-17

- Synchronous execution time bugfix

## 1.0.1 - 2019-05-07

- Check on PCNTL support before registering listeners

## 1.0.0 - 2019-03-22

- First stable release
- Add the ability to catch exceptions by type
- Thrown errors can only have one handler. 
See [UPGRADING](./UPGRADING.md#100) for more information.
