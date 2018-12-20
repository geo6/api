# GEO-6 API

<https://api-v2.geo6.be/>

## Authentication

You will require a *ConsumerId* and a *SecretKey* to access the API. Please [contact us](https://geo6.be/contact.html) to ask for an access.

The API authentication is based on [JSON Web Token](https://jwt.io/).  

Following claims are required:

| Name              | Description                         | Documentation                                       |
| ----------------- | ----------------------------------- | --------------------------------------------------- |
| `iat` (Issued at) | Timestamp when the token is issued. | <https://tools.ietf.org/html/rfc7519#section-4.1.6> |
| `iss` (Issuer)    | Application name.                   | <https://tools.ietf.org/html/rfc7519#section-4.1.1> |
| `sub` (Subject)   | Your *ConsumerId*                   | <https://tools.ietf.org/html/rfc7519#section-4.1.2> |

Supported algorithms are : `HS256`, `HS384`, and `HS512` (using your *SecretKey*).
