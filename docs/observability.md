# Observability

The framework includes benchmark integration for HTTP and CLI runtime.

Benchmark marks are used to make the lifecycle visible during development and diagnostics.

## Runtime marks

The kernels and dispatch flow mark important lifecycle points such as:

- kernel start
- config loaded
- providers registered
- routes registered
- request received
- middleware enter
- route match start
- route matched
- controller resolve start
- controller resolved
- controller action start
- controller action finished
- response created
- response ready
- kernel exception

This is useful for diagnosing slow bootstrap, routing, controller execution or response generation.

## Logging

Logging is registered early during kernel bootstrap so container diagnostics, HTTP handling, CLI failures and kernel exceptions can use the configured logger.

Application code should depend on `Psr\Log\LoggerInterface` when it needs logging.
