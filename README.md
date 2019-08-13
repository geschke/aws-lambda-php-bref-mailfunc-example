# Handle feedback formular and send mail with PHP on AWS Lambda and Bref example.

This is an example of a simple formular handling function with the Bref PHP runtime on AWS Lambda. It makes use of the Slim framework in version 4 to handle the request and generate the JSON response.

## Introduction

Please have a look at the documentation of [Bref](https://bref.sh/docs/) documentation to get an idea of AWS Lambda and serverless. It is necessary to have an environment with Bref and the Serverless framework installed.

This is not a solution to run out-of-the-box, you have to create the HTML formular and JavaScript code on your side! Although, you can take an inspiration from my [feedback formular](https://github.com/geschke/geschke.github.io/blob/master/feedback/index.html) running on my [website](https://www.geschke.net/feedback/).

You have to modify the environment variables in the serverless.yml file to meet your dependencies. 

The secret keys and passwords are stored in AWS Systems Manager Parameter store. To create them with aws cli, you have to enter the following command:

```
$ aws ssm put-parameter --region eu-central-1 --name '/mailfuncsrv/smtp-password' --type String --value 'PASSWORD'
```

To send mails the AWS SES (Simple Email Service) has to be configured (SMTP authentication with username and password, email address verification).

Run composer install or composer update to get PHP packages installed. For local tests it is possible to use the internal PHP webserver. By passing environment variables you can simulate the AWS Lambda environment:

```
$ ENV_FROM_MAIL="info@geschke.net" php -d variables_order=EGPCS -S localhost:3000
```

