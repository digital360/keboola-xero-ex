# Documentation

To use Xero Extractor you just need to create the component in your KBC project and set the configuration correctly.

Set up:

1. Generate public/private key pair: https://developer.xero.com/documentation/advanced-docs/public-private-keypair/

2. Register new Private Application: https://app.xero.com/Application/Add

3. Put privatekey.pem, publickey.cer, consumer key and consumer secret to the KBC File Storage and name it with the tag.

4. Create configuration:

```
{
  "bucket": "in.c-ex-xero-main",

  "consumer_key": "<your_consumer_key>",
  "#consumer_secret": "<your_consumer_secret>",

  "private_key": "<tag_to_private_key>",
  "public_key": "<tag_to_public_key>",

  "parameters": {
  	"fromDate": "2016-01-01",
  	"toDate": "today"
  },

  "report_name": "ProfitAndLoss"
}
```

* bucket - destination bucket in KBC Storage
* consumer_key and consumer_secret - visible after the application registration
* private_key and public_key - name of a tag from KBC File Storage that identifies the certificates
* parameters - parameters passed with the call to API to get the report
* report_name - name of the report to download