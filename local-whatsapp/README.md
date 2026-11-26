# Sheet Sender

A local-only PHP utility that reads the first worksheet from an `.xlsx` file or a `.csv` file, previews personalized messages, and opens WhatsApp with the recipient and message prefilled. The recommended headings are `Phone`, `Name`, and `Message`, allowing every contact row to contain its own message.

The protected **Email sender** tab accepts `Email`, `Name`, `Subject`, and `Message` columns and one optional common attachment (up to 5 MB). Administrators configure an SMTP account in **Email settings**; its password is encrypted at rest. Authorized users preview the complete batch before explicitly confirming delivery. A batch is limited to 100 recipients.

## Run with XAMPP

1. Start Apache from the XAMPP control panel.
2. Open `http://localhost/cmr/local-whatsapp/`.
3. On the first visit, create the administrator account. Use **Manage users** to add senders or other administrators.
4. Sign in with an active account and upload a spreadsheet whose first row contains column headings.
5. Select the phone, optional name, and message columns, then prepare the list. Alternatively, leave the message column unselected and write one shared message using placeholders such as `{Name}`.
6. Review each entry and use **Open WhatsApp** to confirm the send in WhatsApp Web or the installed app.

The tool does not retain uploaded files, write contacts to the CRM database, or use WhatsApp credentials. It reads `.xlsx` files without requiring an extra Composer package or PHP extension. Local users and a small security audit trail are stored in `data/users.sqlite`; Apache is instructed to deny direct web access to that directory.
