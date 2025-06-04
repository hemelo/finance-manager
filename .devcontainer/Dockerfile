# [Choice] PHP version (use -bullseye variants on local arm64/Apple Silicon): 8, 8.1, 8.0, 7, 7.4, 7.3, 8-bullseye, 8.1-bullseye, 8.0-bullseye, 7-bullseye, 7.4-bullseye, 7.3-bullseye, 8-buster, 8.1-buster, 8.0-buster, 7-buster, 7.4-buster
ARG VARIANT=8-bullseye
FROM mcr.microsoft.com/vscode/devcontainers/php:${VARIANT}

RUN apt-get update && apt-get install -y libicu-dev libpng-dev libonig-dev libxml2-dev libzip-dev libpq-dev libicu-dev libmagickwand-dev zip unzip

RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-enable intl

# Install core PHP extensions one by one with error handling
RUN docker-php-ext-install bcmath || echo "bcmath installation failed"
RUN docker-php-ext-install ctype || echo "ctype installation failed"
RUN docker-php-ext-install pdo || echo "pdo installation failed"
RUN docker-php-ext-install pdo_mysql || echo "pdo_mysql installation failed"
RUN docker-php-ext-install pdo_pgsql || echo "pdo_pgsql installation failed"
RUN docker-php-ext-install pcntl || echo "pcntl installation failed"
RUN docker-php-ext-install xml || echo "xml installation failed"
RUN docker-php-ext-install dom || echo "dom installation failed"
RUN docker-php-ext-install fileinfo || echo "fileinfo installation failed"
RUN docker-php-ext-install filter || echo "filter installation failed"
RUN docker-php-ext-install mbstring || echo "mbstring installation failed"
RUN docker-php-ext-install openssl || echo "openssl installation failed"
RUN docker-php-ext-install pcre || echo "pcre installation failed"
RUN docker-php-ext-install session || echo "session installation failed"
RUN docker-php-ext-install simplexml || echo "simplexml installation failed"
RUN docker-php-ext-install zip || echo "zip installation failed"

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Imagick extension
RUN pecl install imagick && docker-php-ext-enable imagick

# [Choice] Node.js version: none, lts/*, 16, 14, 12, 10
ARG NODE_VERSION="none"
RUN if [ "${NODE_VERSION}" != "none" ]; then su vscode -c "umask 0002 && . /usr/local/share/nvm/nvm.sh && nvm install ${NODE_VERSION} 2>&1"; fi

# [Optional] Uncomment this section to install additional OS packages.
# RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
#     && apt-get -y install --no-install-recommends <your-package-list-here>

# [Optional] Uncomment this line to install global node packages.
# RUN su vscode -c "source /usr/local/share/nvm/nvm.sh && npm install -g <your-package-here>" 2>&1