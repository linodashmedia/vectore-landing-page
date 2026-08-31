# Vectore blog — WordPress, served at https://vectore.io/blog
#
# THE SUBPATH, and why it is done this way.
# -----------------------------------------------------------------------------
# The blog has to answer at /blog on the SAME origin as the marketing site, so
# it shares the domain's link equity instead of starting a subdomain from zero.
# There are two ways to arrange that and only one of them is safe:
#
#   ✗ Strip the prefix at the proxy (/blog/foo -> WordPress /foo) and tell
#     WordPress its home is /blog. WordPress then GENERATES links with /blog and
#     RECEIVES requests without it, so its rewrite matching runs against a URL
#     that does not match the home path it was configured with. Permalinks,
#     pagination and previews break in ways that look random.
#
#   ✓ Preserve the prefix end to end (/blog/foo -> WordPress /blog/foo) and make
#     WordPress genuinely live at /blog. That is what happens below: the Apache
#     document root moves to /var/www/site, and /var/www/site/blog is a symlink
#     to the WordPress install. A request for /blog/wp-admin/ then resolves to a
#     real path, and WordPress's own idea of where it lives matches what the
#     browser asked for. Nothing has to be rewritten in flight.
#
# The proxy that fronts this lives in ../server.js.
# -----------------------------------------------------------------------------
FROM wordpress:6.8-php8.3-apache

# mysqli and the opcode cache come with the base image; these are the two the
# base image leaves off that WordPress actually notices the absence of.
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends less; \
	rm -rf /var/lib/apt/lists/*

# --- serve WordPress at /blog -------------------------------------------------
# The symlink is what makes the prefix real. FollowSymLinks is already Apache's
# default, but it is named explicitly in the vhost below so a future base-image
# change cannot silently take it away.
RUN set -eux; \
	mkdir -p /var/www/site; \
	ln -s /var/www/html /var/www/site/blog

COPY config/apache-vectore.conf /etc/apache2/conf-available/vectore.conf
RUN set -eux; \
	a2enmod rewrite headers remoteip; \
	a2enconf vectore

# --- WordPress configuration --------------------------------------------------
# The official image injects WORDPRESS_CONFIG_EXTRA verbatim into wp-config.php.
# Rather than trying to keep a multi-line PHP block inside a Railway environment
# variable, the block is a real file in this repo and the variable is one line
# that loads it. Edit the file, redeploy, done.
COPY config/wp-config-extra.php /usr/local/etc/vectore/wp-config-extra.php
ENV WORDPRESS_CONFIG_EXTRA="require_once '/usr/local/etc/vectore/wp-config-extra.php';"

# --- the theme ----------------------------------------------------------------
# Baked into the image, not mounted. The theme IS this repository, so a deploy
# is the only way it changes, and the running container has no writable copy for
# anyone to hand-edit into a state that Git does not know about.
COPY themes/vectore-blog /usr/src/wordpress/wp-content/themes/vectore-blog
COPY config/mu-plugins /usr/src/wordpress/wp-content/mu-plugins
COPY config/htaccess /usr/src/wordpress/.htaccess

COPY config/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY config/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Railway injects PORT. Apache's ports.conf is rewritten at boot to match, since
# Apache cannot read an environment variable in a Listen directive.
COPY config/docker-entrypoint-vectore.sh /usr/local/bin/docker-entrypoint-vectore.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-vectore.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint-vectore.sh"]
CMD ["apache2-foreground"]
