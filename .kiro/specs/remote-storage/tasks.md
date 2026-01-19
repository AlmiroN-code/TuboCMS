# Implementation Plan

- [x] 1. Create Storage entity




  - Create `src/Entity/Storage.php` with fields: id, name, type, config (JSON), isDefault, isEnabled, createdAt, updatedAt
  - Add validation constraints for required fields
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 2. Create StorageRepository





  - Create `src/Repository/StorageRepository.php`
  - Add methods: findDefault(), findEnabled(), findByType()
  - _Requirements: 1.1, 1.7_

- [x] 3. Update VideoFile entity




  - Add storage relation (ManyToOne, nullable)
  - Add remotePath field (string, nullable)
  - _Requirements: 2.7_

- [x] 4. Create database migration




  - Generate migration for Storage table
  - Add storage_id and remote_path columns to video_file table
  - _Requirements: 1.1, 2.7_

- [x] 5. Write property test for Storage validation





  - **Property 1: Storage validation requires all mandatory fields**
  - **Validates: Requirements 1.2, 1.3, 1.4**

- [x] 6. Create StorageAdapterInterface





  - Create `src/Storage/StorageAdapterInterface.php`
  - Define methods: upload, download, delete, exists, getUrl, getSignedUrl, testConnection, getQuota, createDirectory
  - _Requirements: 2.1, 3.1, 5.1_

- [x] 7. Create ConnectionTestResult and StorageQuota DTOs




  - Create `src/Storage/DTO/ConnectionTestResult.php`
  - Create `src/Storage/DTO/StorageQuota.php`
  - Create `src/Storage/DTO/UploadResult.php`
  - _Requirements: 1.6, 6.2_

- [x] 8. Create AbstractStorageAdapter




  - Create `src/Storage/AbstractStorageAdapter.php`
  - Implement common retry logic with exponential backoff
  - _Requirements: 2.5_

- [x] 9. Write property test for retry logic




  - **Property 4: Retry logic with exponential backoff**
  - **Validates: Requirements 2.5**

- [x] 10. Create FtpStorageAdapter




  - Create `src/Storage/Adapter/FtpStorageAdapter.php`
  - Implement all interface methods using PHP FTP functions
  - Support passive mode and SSL/TLS
  - _Requirements: 2.2, 5.2_

- [x] 11. Create FtpStorageAdapterFactory





  - Create factory to instantiate adapter from Storage entity config
  - _Requirements: 1.2_

- [x] 12. Create SftpStorageAdapter










  - Create `src/Storage/Adapter/SftpStorageAdapter.php`
  - Implement using phpseclib3 library
  - Support password and key authentication
  - _Requirements: 2.3, 5.3_

- [x] 13. Create SftpStorageAdapterFactory





  - Create factory to instantiate adapter from Storage entity config
  - _Requirements: 1.3_

- [x] 14. Create HttpStorageAdapter






  - Create `src/Storage/Adapter/HttpStorageAdapter.php`
  - Implement using Symfony HttpClient
  - Support configurable endpoints and auth headers
  - _Requirements: 2.4, 5.4_

- [x] 15. Create HttpStorageAdapterFactory




  - Create factory to instantiate adapter from Storage entity config
  - _Requirements: 1.4_

- [x] 16. Create LocalStorageAdapter




  - Create `src/Storage/Adapter/LocalStorageAdapter.php`
  - Implement for local filesystem (existing behavior)
  - _Requirements: 2.1_

- [x] 17. Create StorageManager service




  - Create `src/Service/StorageManager.php`
  - Implement getAdapter(), getDefaultStorage(), uploadFile(), deleteFile(), getFileUrl(), migrateFile()
  - _Requirements: 1.7, 2.1, 3.1, 4.3_

- [x] 18. Write property test for default storage usage





  - **Property 2: Default storage is used for new uploads**
  - **Validates: Requirements 1.7**

- [x] 19. Write property test for disabled storage rejection




  - **Property 3: Disabled storage rejects uploads**
  - **Validates: Requirements 1.8**

- [x] 20. Write property test for URL generation








  - **Property 6: URL generation matches storage type**
  - **Validates: Requirements 3.1, 3.3**

- [x] 21. Create UploadToStorageMessage





  - Create `src/Message/UploadToStorageMessage.php`
  - _Requirements: 2.1_

- [x] 22. Create UploadToStorageMessageHandler





  - Create `src/MessageHandler/UploadToStorageMessageHandler.php`
  - Implement upload with retry logic
  - Handle failure notification
  - _Requirements: 2.1, 2.5, 2.6_

- [x] 23. Write property test for successful upload path storage





  - **Property 5: Successful upload stores remote path**
  - **Validates: Requirements 2.7**

- [x] 24. Create DeleteFromStorageMessage and Handler




  - Create message and handler for async file deletion
  - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [x] 25. Write property test for deletion job creation





  - **Property 10: Deletion queues jobs for all associated files**
  - **Validates: Requirements 5.1**

- [x] 26. Create MigrateFileMessage and Handler







  - Create message and handler for file migration
  - _Requirements: 4.2, 4.3_

- [x] 27. Write property test for migration job creation





  - **Property 8: Migration creates jobs for all files**
  - **Validates: Requirements 4.2**

- [x] 28. Checkpoint - Core functionality




  - Ensure all tests pass, ask the user if questions arise.

- [x] 29. Implement signed URL generation




  - Add signature and expiration to URLs
  - Create URL verification service
  - _Requirements: 3.4_

- [x] 30. Write property test for signed URLs




  - **Property 7: Signed URLs contain signature and expiration**
  - **Validates: Requirements 3.4**

- [x] 31. Create proxy controller for FTP/SFTP files





  - Create `src/Controller/StorageProxyController.php`
  - Stream files from FTP/SFTP through proxy
  - _Requirements: 3.2_

- [x] 32. Create AdminStorageController





  - Create `src/Controller/Admin/AdminStorageController.php`
  - Implement index, create, edit, delete, test actions
  - _Requirements: 1.1, 1.5, 1.6_

- [x] 33. Create storage form templates




  - Create `templates/admin/storage/index.html.twig`
  - Create `templates/admin/storage/form.html.twig`
  - Add type-specific config fields (FTP, SFTP, HTTP)
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 34. Implement connection test endpoint




  - Add AJAX endpoint for testing storage connection
  - Return detailed error messages on failure
  - _Requirements: 1.6_

- [x] 35. Implement set default storage action





  - Add action to set storage as default
  - Ensure only one default at a time
  - _Requirements: 1.7_

- [x] 36. Implement enable/disable storage action





  - Add toggle for storage enabled status
  - _Requirements: 1.8_

- [x] 37. Create migration interface





  - Add migration page to admin panel
  - Allow selecting source and destination storages
  - _Requirements: 4.1_

- [x] 38. Implement migration initiation







  - Queue migration jobs for selected files
  - Show progress indicator
  - _Requirements: 4.2_

- [x] 39. Implement migration report




  - Create `src/Service/MigrationReportService.php`
  - Track success/failure counts
  - Display summary after completion
  - _Requirements: 4.5_

- [x] 40. Write property test for migration report accuracy





  - **Property 9: Migration report accuracy**
  - **Validates: Requirements 4.5**

- [x] 41. Create StorageStatsService





  - Create `src/Service/StorageStatsService.php`
  - Calculate files count and total size per storage
  - _Requirements: 6.1_

- [x] 42. Write property test for statistics accuracy





  - **Property 11: Storage statistics accuracy**
  - **Validates: Requirements 6.1**

- [x] 43. Add storage dashboard widget




  - Display storage usage on admin dashboard
  - Show quota information if available
  - _Requirements: 6.1, 6.2_

- [x] 44. Implement usage warning




  - Show warning when usage exceeds 80%
  - _Requirements: 6.3_

- [x] 45. Write property test for warning threshold





  - **Property 12: Warning threshold at 80%**
  - **Validates: Requirements 6.3**

- [x] 46. Update ProcessVideoEncodingMessageHandler





  - Integrate StorageManager for uploading processed files
  - Upload poster, preview, and encoded files to remote storage
  - _Requirements: 2.1_

- [x] 47. Update VideoController for remote playback




  - Use StorageManager to get video URLs
  - Support proxy and direct URLs based on storage type
  - _Requirements: 3.1, 3.2, 3.3_

- [x] 48. Update video deletion logic




  - Queue deletion jobs for remote files when video is deleted
  - _Requirements: 5.1_



- [x] 49. Final Checkpoint



  - Ensure all tests pass, ask the user if questions arise.
