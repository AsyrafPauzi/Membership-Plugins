Simple Member Database Manager (SMDM)
Simple Member Database Manager is a lightweight, high-performance, and admin-centric membership management system for WordPress. Unlike heavy membership plugins, SMDM focuses on providing a clean, SaaS-style interface for administrators to manage a private member database, send bulk email blasts via SMTP, and display a modern member directory on the frontend.
![alt text](https://img.shields.io/badge/license-GPL--2.0-blue.svg)

![alt text](https://img.shields.io/badge/WordPress-6.0%2B-0073aa.svg)

![alt text](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)
🚀 Key Features
🖥️ SaaS-Style Admin UI
Custom Admin Shell: Bypasses the traditional WordPress backend look for a modern, clean, "Software-as-a-Service" feel.
Interactive Dashboard: Real-time analytics using Chart.js showing member growth trends and status composition.
Custom Member Manager: Full CRUD (Create, Read, Update, Delete) capability without using the standard WordPress post editor.
🇲🇾 Malaysian Localized Fields
Specially designed for Malaysian organizations with fields for:
IC / Passport Number
State Dropdown (All 13 states + 3 Federal Territories)
Gender & Date of Birth
Full Address & Postcode
📧 Advanced SMTP Email Blast
Batch Sending: Uses AJAX to send emails in chunks (e.g., 20 at a time) to prevent server timeouts and "White Screen of Death."
Spam Protection: Sends individual emails (no BCC) and includes rate-limiting to protect your SMTP reputation.
Custom SMTP Config: Built-in SMTP settings (Host, Port, User, Pass) to ensure high deliverability via providers like Gmail, MailerSend, or cPanel.
Progress Tracking: Real-time progress bar and log box during email blasts.
🔍 Modern Frontend Directory
Shortcode Powered: Simply use [member_directory] to display your list.
Advanced Filtering: Search by Name, Category, State, or City.
Member Popups: Users can click "View Profile" to see a modern modal/popup with full member details without leaving the page.
📤 Data Portability
CSV Import: Quickly upload hundreds of members using a formatted CSV.
CSV Export: Full database backup including all Malaysian-specific fields.
📂 Folder Structure
code
Text
simple-member-database-manager/
├── assets/
│   ├── css/
│   │   ├── admin-style.css      # Custom SaaS UI styles
│   │   └── frontend-style.css   # Modern directory & modal styles
│   └── js/
│       └── frontend.js          # Handles modal/popup logic
├── includes/
│   ├── class-smdm-admin-pages.php    # Dashboard & Custom UI logic
│   ├── class-smdm-email-handler.php   # SMTP & AJAX Batching
│   ├── class-smdm-frontend.php        # Shortcode & Directory logic
│   ├── class-smdm-import-export.php   # CSV processing
│   ├── class-smdm-post-type.php       # Member CPT registration
│   ├── class-smdm-taxonomy.php        # Member Categories
│   └── class-smdm-meta-boxes.php      # (Optional) Standard meta hooks
└── simple-member-database-manager.php # Main entry point
🛠️ Installation
Download the repository as a ZIP file.
Upload the folder to your WordPress site via Plugins > Add New > Upload Plugin.
Activate the plugin.
Navigate to the Member Manager menu in your WordPress sidebar.
Important: Configure your SMTP Settings before sending your first email blast.
📖 Usage
Displaying the Directory
To show the member directory on any page or post:
[member_directory]
Importing Data
Ensure your CSV follows this exact column order:
Name, IC, Gender, DOB, Email, Phone, Address, Postcode, City, State, Category, Status
🛡️ Security & Performance
Nonces: All forms are protected by WordPress Nonces to prevent CSRF attacks.
Sanitization: All inputs are sanitized using sanitize_text_field, sanitize_email, etc.
Escaping: All outputs are escaped using esc_html and esc_attr.
AJAX Batching: Prevents PHP execution timeouts on large databases (500+ members).
📝 Requirements
WordPress 6.0 or higher.
PHP 7.4 or higher.
SMTP Credentials: (Recommended) Use a dedicated provider like MailerSend, Brevo, or your cPanel email for high-volume blasts.
🤝 Contributing
Feel free to fork this project and submit pull requests. For major changes, please open an issue first to discuss what you would like to change.
📄 License
This project is licensed under the GPL-2.0 License.
How to use this on GitHub:
Create a new file in your repository named README.md.
Paste the text above into it.
GitHub will automatically format it with bold headers, icons, and code blocks!
