# PowerShell Database Management Tools

## Overview
A collection of PowerShell scripts for database management and maintenance, focusing on SQL Server backup automation, monitoring, and maintenance tasks.

## Features
- Automated SQL Server database backups
- Backup file compression
- Customizable retention policies
- Email notifications
- Error handling and logging
- Network share support
- Multiple server support
- Backup verification
- Space management

## Scripts

### SQL Server Backup Script (sql_backup.ps1)
Performs automated backups of SQL Server databases with the following features:
- Full, differential, and transaction log backups
- Customizable backup schedules
- Compression options
- Verification after backup
- Cleanup of old backup files
- Email notifications for success/failure
- Detailed logging

## Requirements
- Windows PowerShell 5.1 or later
- SQL Server Management Objects (SMO)
- SQL Server instance with appropriate permissions
- Email server (for notifications)
- Sufficient disk space for backups

## Installation

1. Clone the repository:
```powershell
git clone https://github.com/yourusername/powershell-tools.git
cd powershell-tools
```

2. Configure backup settings:
   - Edit `config/backup-config.json` with your settings
   - Set up email configuration if needed
   - Adjust retention policies

## Configuration

### Backup Settings (backup-config.json)
```json
{
    "SqlServer": {
        "Instance": "ServerName\\InstanceName",
        "Authentication": "Windows",
        "Username": "",
        "Password": ""
    },
    "Backup": {
        "Path": "D:\\Backups",
        "RetentionDays": 30,
        "Compression": true,
        "Verify": true
    },
    "Email": {
        "SmtpServer": "smtp.company.com",
        "From": "backup@company.com",
        "To": ["admin@company.com"],
        "Subject": "SQL Backup Status"
    }
}
```

## Usage

### Running Backup Script
```powershell
.\src\sql_backup.ps1 -ConfigPath "config\backup-config.json"
```

### Scheduling Backups
To schedule regular backups:
1. Open Task Scheduler
2. Create a new task
3. Set trigger (e.g., daily at 2 AM)
4. Action: Start PowerShell with script path
5. Set appropriate credentials

## Logging
- Logs are stored in the `logs` directory
- Each backup operation creates detailed log entries
- Error logs include full stack traces
- Log rotation is automatic (30 days)

## Security Features
- Windows Authentication support
- Encrypted password storage
- Minimal permission requirements
- Network path security
- Audit logging

## Error Handling
- Connection failures
- Disk space checks
- Backup corruption detection
- Network path availability
- Permission validation

## Project Structure
```
powershell-tools/
├── src/
│   └── sql_backup.ps1     # Main backup script
├── config/
│   └── backup-config.json # Configuration file
├── logs/                  # Log files
└── README.md
```

## Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

## License
MIT License - see the [LICENSE](LICENSE) file for details
