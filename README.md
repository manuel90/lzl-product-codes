## LzL Product Codes - WP Plugin

## About the repository/plugin

* Wordpress plugin
* Version 0.0.1
* Tested in Wordpress version 4.9.6
* Use custom table

## SQL

```
CREATE TABLE IF NOT EXISTS wp_product_codes_lzl (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, 
    `code` varchar(255) NOT NULL UNIQUE,
    `post_id` bigint(20) unsigned NOT NULL,
    `user_email` varchar(100),
    `status` char(1) DEFAULT '0' comment "0=available, 1=assign, 2=used, 3=pending",
    CONSTRAINT Pk_wp_product_codes_lzl PRIMARY KEY (`id`),
    CONSTRAINT Fk_posts FOREIGN KEY (`post_id`) REFERENCES wp_posts(ID)
);
```

## Power by

### Manuel ###