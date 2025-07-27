# SQL Server Backup Script
# Author: Your Name
# Version: 2.0
# Description: Automated SQL Server backup script with comprehensive error handling and logging

#Requires -Version 5.1
#Requires -Modules SqlServer

[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)]
    [string]$ConfigPath
)

# Script initialization
$ErrorActionPreference = "Stop"
$script:currentDate = Get-Date
$script:scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path

# Import configuration
function Import-BackupConfig {
    try {
        $config = Get-Content -Path $ConfigPath -Raw | ConvertFrom-Json
        Write-Log "Configuration loaded successfully"
        return $config
    }
    catch {
        Write-Error "Failed to load configuration: $_"
        exit 1
    }
}

# Logging function
function Write-Log {
    param(
        [string]$Message,
        [ValidateSet('Information','Warning','Error')]
        [string]$Level = 'Information'
    )
    
    $logMessage = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')|$Level|$Message"
    $logFile = Join-Path $config.Logging.Path "SQL_Backup_$(Get-Date -Format 'yyyy-MM-dd').log"
    
    # Ensure log directory exists
    if (-not (Test-Path (Split-Path $logFile))) {
        New-Item -ItemType Directory -Path (Split-Path $logFile) -Force | Out-Null
    }
    
    Add-Content -Path $logFile -Value $logMessage
    
    switch ($Level) {
        'Information' { Write-Host $Message }
        'Warning' { Write-Host $Message -ForegroundColor Yellow }
        'Error' { Write-Host $Message -ForegroundColor Red }
    }
}

# Email notification function
function Send-BackupNotification {
    param(
        [string]$Subject,
        [string]$Body,
        [bool]$IsError = $false
    )
    
    if (-not $config.Email.Enabled) { return }
    
    try {
        $emailParams = @{
            From = $config.Email.From
            To = $config.Email.To
            Subject = "$($config.Email.Subject) - $Subject"
            Body = $Body
            SmtpServer = $config.Email.SmtpServer
            Port = $config.Email.Port
            UseSsl = $config.Email.UseSsl
        }
        
        Send-MailMessage @emailParams
        Write-Log "Email notification sent successfully"
    }
    catch {
        Write-Log "Failed to send email notification: $_" -Level Warning
    }
}

# Check disk space
function Test-DiskSpace {
    param([string]$Path)
    
    try {
        $drive = (Get-Item $Path).PSDrive
        $freeSpace = (Get-PSDrive $drive.Name).Free / 1GB
        
        if ($freeSpace -lt $config.Maintenance.MinimumFreeSpaceGB) {
            throw "Insufficient disk space. Only ${freeSpace}GB available."
        }
        
        Write-Log "Disk space check passed. ${freeSpace}GB available."
        return $true
    }
    catch {
        Write-Log "Disk space check failed: $_" -Level Error
        return $false
    }
}

# Cleanup old backups
function Remove-OldBackups {
    param([string]$BackupPath)
    
    try {
        $cutoffDate = (Get-Date).AddDays(-$config.Backup.RetentionDays)
        Get-ChildItem -Path $BackupPath -File |
            Where-Object { $_.LastWriteTime -lt $cutoffDate } |
            ForEach-Object {
                Remove-Item $_.FullName -Force
                Write-Log "Removed old backup: $($_.Name)"
            }
    }
    catch {
        Write-Log "Failed to cleanup old backups: $_" -Level Warning
    }
}

# Verify backup
function Test-BackupFile {
    param(
        [string]$BackupFile,
        [string]$DatabaseName
    )
    
    try {
        $verifyQuery = "RESTORE VERIFYONLY FROM DISK = N'$BackupFile'"
        Invoke-Sqlcmd -ServerInstance $config.SqlServer.Instance -Query $verifyQuery
        Write-Log "Backup verified successfully: $BackupFile"
        return $true
    }
    catch {
        Write-Log "Backup verification failed: $_" -Level Error
        return $false
    }
}

# Perform database backup
function Backup-SqlDatabase {
    param(
        [string]$DatabaseName,
        [ValidateSet('Full','Differential','Log')]
        [string]$BackupType
    )
    
    try {
        # Generate backup file name
        $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
        $backupFile = Join-Path $config.Backup.Path "$DatabaseName`_$BackupType`_$timestamp.bak"
        
        # Create backup command
        $backupParams = @{
            ServerInstance = $config.SqlServer.Instance
            Database = $DatabaseName
            BackupFile = $backupFile
            CompressionOption = if ($config.Backup.Compression) { "On" } else { "Off" }
        }
        
        if ($BackupType -eq 'Differential') {
            $backupParams.Incremental = $true
        }
        elseif ($BackupType -eq 'Log') {
            $backupParams.BackupAction = "Log"
        }
        
        # Perform backup
        Backup-SqlDatabase @backupParams
        Write-Log "Backup completed: $backupFile"
        
        # Verify backup if enabled
        if ($config.Backup.Verify) {
            if (-not (Test-BackupFile -BackupFile $backupFile -DatabaseName $DatabaseName)) {
                throw "Backup verification failed"
            }
        }
        
        return $true
    }
    catch {
        Write-Log "Backup failed for $DatabaseName : $_" -Level Error
        return $false
    }
}

# Main backup process
function Start-SqlBackup {
    try {
        Write-Log "Starting SQL Server backup process"
        
        # Check disk space
        if ($config.Maintenance.CheckDiskSpace) {
            if (-not (Test-DiskSpace -Path $config.Backup.Path)) {
                throw "Disk space check failed"
            }
        }
        
        # Clean up old backups
        if ($config.Maintenance.CleanupOldFiles) {
            Remove-OldBackups -BackupPath $config.Backup.Path
        }
        
        # Get databases to backup
        $databases = if ($config.SqlServer.Databases) {
            $config.SqlServer.Databases
        }
        else {
            Get-SqlDatabase -ServerInstance $config.SqlServer.Instance |
                Where-Object { $_.Name -notin $config.SqlServer.ExcludedDatabases } |
                Select-Object -ExpandProperty Name
        }
        
        $successCount = 0
        $failureCount = 0
        
        foreach ($database in $databases) {
            Write-Log "Processing backup for database: $database"
            
            if ($config.Backup.Types.Full.Enabled) {
                if (Backup-SqlDatabase -DatabaseName $database -BackupType Full) {
                    $successCount++
                }
                else {
                    $failureCount++
                }
            }
            
            if ($config.Backup.Types.Differential.Enabled) {
                if (Backup-SqlDatabase -DatabaseName $database -BackupType Differential) {
                    $successCount++
                }
                else {
                    $failureCount++
                }
            }
            
            if ($config.Backup.Types.TransactionLog.Enabled) {
                if (Backup-SqlDatabase -DatabaseName $database -BackupType Log) {
                    $successCount++
                }
                else {
                    $failureCount++
                }
            }
        }
        
        # Send notification
        $subject = if ($failureCount -eq 0) { "Backup Completed Successfully" } else { "Backup Completed with Errors" }
        $body = @"
SQL Server Backup Summary
------------------------
Server: $($config.SqlServer.Instance)
Time: $(Get-Date)
Successful Backups: $successCount
Failed Backups: $failureCount
"@
        
        if (($failureCount -eq 0 -and $config.Email.NotifyOnSuccess) -or
            ($failureCount -gt 0 -and $config.Email.NotifyOnFailure)) {
            Send-BackupNotification -Subject $subject -Body $body -IsError ($failureCount -gt 0)
        }
        
        Write-Log "Backup process completed. Success: $successCount, Failures: $failureCount"
    }
    catch {
        Write-Log "Critical error in backup process: $_" -Level Error
        Send-BackupNotification -Subject "Critical Backup Error" -Body $_.Exception.Message -IsError $true
        exit 1
    }
}

# Script execution
try {
    $config = Import-BackupConfig
    Start-SqlBackup
}
catch {
    Write-Log "Fatal error: $_" -Level Error
    exit 1
}
