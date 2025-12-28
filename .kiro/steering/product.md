# RexTube - Product Overview

## What is RexTube?

RexTube is a video hosting platform built with Symfony 8.0, similar to adult video hosting sites. It's a content management system that allows users to upload, browse, search, and comment on videos.

## Core Features

- User authentication and registration
- Video upload (up to 2GB)
- Video playback with player
- Video categorization and tagging
- Full-text search
- Nested comments with HTMX
- User dashboard (my videos)
- Admin panel for content management
- Email notifications
- Rate limiting for abuse prevention
- Asynchronous video processing via Messenger

## Key Entities

- **User**: Registered users with authentication
- **Video**: Uploaded video content with metadata
- **Category**: Video categorization
- **Tag**: Video tagging system
- **Comment**: User comments with nesting support
- **VideoFile**: Processed video files with encoding profiles
- **VideoEncodingProfile**: Different quality/format options for videos
- **SiteSetting**: Global site configuration

## Current Status

The project is fully functional with core features implemented. Video processing pipeline is in place using Symfony Messenger for asynchronous handling.

## Target Users

- Content creators (uploaders)
- Content consumers (viewers)
- Site administrators

Всегда пиши и отвечай на русском языке
