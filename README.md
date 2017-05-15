# Documentation

To use Xero Extractor you just need to create the component in your KBC project and set the configuration correctly.

Set up:

1. Generate public/private key pair: https://developer.xero.com/documentation/advanced-docs/public-private-keypair/

2. Register new Private Application: https://app.xero.com/Application/Add

3. Put privatekey.pem, publickey.cer, consumer key and consumer secret to the configuration. Before including cerficates, replace all newlines with "\n" string.

4. Create configuration:

```
{
  "bucket": "in.c-ex-xero-main",

  "consumer_key": "<your_consumer_key>",
  "#consumer_secret": "<your_consumer_secret>",

  "#private_key": "<private_key>",
  "public_key": "<public_key>",

  "parameters": {
  	"fromDate": "2016-01-01",
  	"toDate": "today"
  },

  "endpoint": [
    "ProfitAndLoss",
    "Contacts"
  ]
}
```

* bucket - destination bucket in KBC Storage
* consumer_key and consumer_secret - visible after the application registration
* private_key and public_key - certificates
* parameters - parameters passed with the call to API to get the report
* endpoint - name of the endpoints to download - might be a string or an array