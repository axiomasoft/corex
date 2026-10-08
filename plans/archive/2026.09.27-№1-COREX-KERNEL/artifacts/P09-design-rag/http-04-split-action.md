# Primary source capture

Date: 2026-10-06
Tool: urllib.request GET
URL: https://raw.githubusercontent.com/danharrin/monorepo-split-github-action/64498811856d440ceeea37737feac43ffcb2c623/action.yaml
SHA256: ff1b8abe00305dc2206c8c9a8a4ad4194f1e6162fe911d7b4dd550c5e0671458

```text
# see https://docs.github.com/en/actions/creating-actions/metadata-syntax-for-github-actions#about-yaml-syntax-for-github-actions

name: 'Monorepo Split'
description: 'Split monorepo packages to many repositories'
author: "Tomas Votruba <tomas.vot@gmail.com>"

inputs:
    package_directory:
        description: 'Local package directory'
        required: true
    repository_host:
        description: 'Host'
        default: 'github.com'
        required: false
    repository_organization:
        description: 'Remote organization'
        required: true
    repository_name:
        description: 'Remote repository'
        required: true
    branch:
        description: 'Git branch'
        default: 'main'
        required: true
    tag:
        description: '[Optional] Tag to be released'
        required: false
    user_email:
        description: 'Git User Email'
        required: true
    user_name:
        description: 'Git User Name'
        required: true

runs:
    using: 'docker'
    image: 'Dockerfile'
    args:
        # "GitHub stores input parameters as environment variables" - important!!!
        - ${{ inputs.package_directory }} # => INPUT_PACKAGE_DIRECTORY
        - ${{ inputs.repository_host }}
        - ${{ inputs.repository_organization }}
        - ${{ inputs.repository_name }}
        - ${{ inputs.branch }}
        - ${{ inputs.tag }}
        - ${{ inputs.user_email }}
        - ${{ inputs.user_name }}

branding:
    icon: maximize-2
    color: purple

```
