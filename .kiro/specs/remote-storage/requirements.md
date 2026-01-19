# Requirements Document

## Introduction

Функционал удалённых хранилищ позволяет RexTube загружать и хранить видеофайлы на внешних серверах вместо локальной файловой системы. Поддерживаются протоколы FTP, SFTP и HTTP/HTTPS для взаимодействия с удалёнными серверами. Администраторы могут настраивать несколько хранилищ и выбирать, куда загружать новые видео.

## Glossary

- **Storage**: Удалённое хранилище для видеофайлов
- **Storage Adapter**: Компонент, реализующий взаимодействие с конкретным типом хранилища
- **FTP**: File Transfer Protocol — протокол передачи файлов
- **SFTP**: SSH File Transfer Protocol — защищённый протокол передачи файлов
- **Remote Server**: Удалённый HTTP/HTTPS сервер с API для загрузки файлов

## Requirements

### Requirement 1

**User Story:** As an administrator, I want to configure remote storage connections, so that I can store videos on external servers.

#### Acceptance Criteria

1. WHEN an administrator accesses the storage settings page THEN the System SHALL display a list of configured storages with their status
2. WHEN an administrator creates a new FTP storage THEN the System SHALL require host, port, username, password, and base path fields
3. WHEN an administrator creates a new SFTP storage THEN the System SHALL require host, port, username, authentication method (password or key), and base path fields
4. WHEN an administrator creates a new Remote Server storage THEN the System SHALL require base URL, authentication token, and upload endpoint fields
5. WHEN an administrator saves storage configuration THEN the System SHALL validate connection parameters before saving
6. WHEN an administrator tests storage connection THEN the System SHALL attempt to connect and report success or detailed error message
7. WHEN an administrator sets a storage as default THEN the System SHALL use this storage for all new video uploads
8. WHEN an administrator disables a storage THEN the System SHALL prevent new uploads to this storage while keeping existing files accessible

### Requirement 2

**User Story:** As a system, I want to upload video files to remote storage, so that videos are stored on external servers.

#### Acceptance Criteria

1. WHEN a video is processed THEN the System SHALL upload all generated files (original, transcoded, preview, poster) to the configured default storage
2. WHEN uploading to FTP storage THEN the System SHALL create necessary directory structure and transfer files using FTP protocol
3. WHEN uploading to SFTP storage THEN the System SHALL create necessary directory structure and transfer files using SFTP protocol
4. WHEN uploading to Remote Server THEN the System SHALL send files via HTTP POST/PUT requests to the configured endpoint
5. IF upload fails THEN the System SHALL retry up to 3 times with exponential backoff
6. IF all upload attempts fail THEN the System SHALL mark the video as failed and notify the administrator
7. WHEN upload succeeds THEN the System SHALL store the remote file path in the VideoFile entity

### Requirement 3

**User Story:** As a user, I want to watch videos stored on remote servers, so that I can access content regardless of storage location.

#### Acceptance Criteria

1. WHEN a user requests a video THEN the System SHALL generate appropriate URL based on storage type
2. WHEN video is stored on FTP/SFTP THEN the System SHALL serve the file through a proxy endpoint or generate a temporary download URL
3. WHEN video is stored on Remote Server THEN the System SHALL return the direct URL to the remote file
4. WHEN generating video URLs THEN the System SHALL support optional signed URLs with expiration for security

### Requirement 4

**User Story:** As an administrator, I want to migrate videos between storages, so that I can reorganize content distribution.

#### Acceptance Criteria

1. WHEN an administrator initiates migration THEN the System SHALL allow selecting source and destination storages
2. WHEN migration is started THEN the System SHALL queue migration jobs for each video file
3. WHEN a file is migrated THEN the System SHALL copy the file to destination, verify integrity, and update the VideoFile record
4. IF migration fails for a file THEN the System SHALL log the error and continue with remaining files
5. WHEN migration completes THEN the System SHALL provide a summary report with success/failure counts

### Requirement 5

**User Story:** As an administrator, I want to delete files from remote storage, so that I can free up space when videos are removed.

#### Acceptance Criteria

1. WHEN a video is deleted THEN the System SHALL queue deletion jobs for all associated remote files
2. WHEN deleting from FTP storage THEN the System SHALL remove the file using FTP DELETE command
3. WHEN deleting from SFTP storage THEN the System SHALL remove the file using SFTP unlink command
4. WHEN deleting from Remote Server THEN the System SHALL send HTTP DELETE request to the configured endpoint
5. IF deletion fails THEN the System SHALL log the error for manual cleanup

### Requirement 6

**User Story:** As an administrator, I want to monitor storage usage, so that I can track capacity and costs.

#### Acceptance Criteria

1. WHEN viewing storage dashboard THEN the System SHALL display total files count and estimated size per storage
2. WHEN a storage supports quota information THEN the System SHALL display used and available space
3. WHEN storage usage exceeds 80% THEN the System SHALL display a warning notification

Используется D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe