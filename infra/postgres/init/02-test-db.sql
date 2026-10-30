-- Окрема база для автотестів (phpunit.xml), щоб RefreshDatabase не чіпав базу розробки
CREATE DATABASE logistics_test OWNER logistics;
\connect logistics_test
CREATE EXTENSION IF NOT EXISTS postgis;
