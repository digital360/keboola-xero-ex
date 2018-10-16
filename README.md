# Documentation

To use Xero Extractor you just need to create the component in your KBC project and set the configuration correctly.

Set up:

1. Generate public/private key pair: https://developer.xero.com/documentation/api-guides/create-publicprivate-key

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
    "Journals",
    {
      "Contacts": [
        {"includeArchived": "true"}
      ]
    }
  ]
}
```

* bucket - destination bucket in KBC Storage
* consumer_key and consumer_secret - visible after the application registration
* private_key and public_key - certificates
* parameters - parameters passed with the call to API to get the report - global parameters added to all API requests
* endpoint - name of the endpoints to download - might be a string, array or array of arrays
  * endpoint parameters - you can turn single endpoint string into and object and add an array of parameters you want to apply on the particular endpoint - see the example

## Endpoints with custom pagination

1. Bank Transaction: https://api.xero.com/api.xro/2.0/BankTransactions --> Page 
2. Contacts : https://api.xero.com/api.xro/2.0/Contacts --> Include Archived and Page 
3. Invoices : https://api.xero.com/api.xro/2.0/Invoices --> Page 
4. Journals : https://api.xero.com/api.xro/2.0/Journals --> Offset 
5. Overpayments : https://api.xero.com/api.xro/2.0/Overpayments --> Page 
6. Prepayments : https://api.xero.com/api.xro/2.0/Prepayments --> Page 
7. Purchase Orders: https://api.xero.com/api.xro/2.0/PurchaseOrders --> Page ",Date from and Date to", and Status 
8. Tracking Categories : https://api.xero.com/api.xro/2.0/TrackingCategories --> Include Archived

Page 

BankTransactions
Contacts
Invoices
Overpayments
Prepayments
PurchaseOrders


Offset

Journals




