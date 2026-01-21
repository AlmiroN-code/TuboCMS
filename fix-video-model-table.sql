-- Исправление таблицы video_model на продакшн сервере
-- Выполнить на сервере: mysql -u almiron -p'Mtn999Un86@' sexvids < fix-video-model-table.sql

USE sexvids;

-- Удаляем старую таблицу
DROP TABLE IF EXISTS video_model;

-- Создаем правильную таблицу
CREATE TABLE video_model (
    video_id INT NOT NULL,
    model_profile_id INT NOT NULL,
    PRIMARY KEY (video_id, model_profile_id),
    INDEX IDX_video_model_video (video_id),
    INDEX IDX_video_model_model (model_profile_id),
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    FOREIGN KEY (model_profile_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Проверяем что таблица создана правильно
DESCRIBE video_model;

-- Показываем количество записей
SELECT COUNT(*) as total_records FROM video_model;