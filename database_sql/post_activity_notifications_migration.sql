-- Enable notifications when members like, comment on, share, or save a post.
-- Run after dashboard_features_migration.sql and request_response_messages_migration.sql.

ALTER TABLE `member_notifications`
  MODIFY `notification_type` ENUM('request_response','request_message','post_like','post_comment','post_share','post_save') NOT NULL;
