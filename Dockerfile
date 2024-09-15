FROM php:8.0-apache

ARG APP_NAME="daltonplan"
ARG APP_VER="0.1"

LABEL \
    maintainer="Lava Block" \
    org.label-schema.name="${APP_NAME}" \
    org.label-schema.version="${APP_VER}" \
    org.label-schema.schema-version="2.0" \
    org.label-schema.vendor="Lava Block" \
    org.label-schema.url="https://daltonplan.com" \
    org.label-schema.vcs-url="https://github.com/daltonplan/daltonplan"

COPY [".", "/usr/src/${APP_NAME}"]
WORKDIR "/usr/src/${APP_NAME}"
CMD [ "php", "./index.php" ]
