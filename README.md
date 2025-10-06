# Mojo CLI Tool

Cross-platform PHP CLI for Mojo's Infisical secrets management.

## 🚀 Installation

### Global Installation (Recommended)
```bash
composer global require mojo/cli
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

### Local Installation
```bash
git clone this-repo
cd mojo-cli
composer install
```

## 🔧 One-Time Setup (Permanent)

### Set Environment Variables

**Bash users:**
```bash
# Add to .bashrc permanently
echo 'export INFISICAL_CLIENT_ID="your-client-id"' >> ~/.bashrc
echo 'export INFISICAL_CLIENT_SECRET="your-client-secret"' >> ~/.bashrc

# Reload .bashrc in current session
source ~/.bashrc

# Verify variables are saved in .bashrc
grep "INFISICAL" ~/.bashrc
```

**Zsh users:**
```bash
# Add to .zshrc permanently
echo 'export INFISICAL_CLIENT_ID="your-client-id"' >> ~/.zshrc
echo 'export INFISICAL_CLIENT_SECRET="your-client-secret"' >> ~/.zshrc

# Reload .zshrc in current session
source ~/.zshrc

# Verify variables are saved in .zshrc
grep "INFISICAL" ~/.zshrc
```

**Manual setup:**
```bash
# Edit your shell profile
vim ~/.bashrc  # or ~/.zshrc

# Add these lines:
export INFISICAL_CLIENT_ID="your-client-id"
export INFISICAL_CLIENT_SECRET="your-client-secret"

# Save and reload
source ~/.bashrc
```

### Verify Setup
```bash
# Check variables are loaded in current session
echo $INFISICAL_CLIENT_ID
echo $INFISICAL_CLIENT_SECRET

# Check variables are saved in shell profile
grep "INFISICAL" ~/.zshrc     # For Zsh users
grep "INFISICAL" ~/.bashrc    # For Bash users

# Verify variables persist in new terminal
# (Open a new terminal and run):
echo "Client ID: $INFISICAL_CLIENT_ID"
```

## 📋 Commands

### Initialize Project
```bash
# Interactive environment selection
mojocliinit --name="Project Name"

# Specify environment directly
mojocliinit --name="Project Name" --env="dev"
mojocliinit --name="Project Name" --env="staging"
mojocliinit --name="Project Name" --env="production"
```
Creates `infisical.json` in current directory.

### Fetch Secrets
```bash
# Interactive environment selection with custom output
mojoclistartup --output=.env.local

# Specify environment and output file
mojoclistartup --env=dev --output=.env.local
mojoclistartup --env=staging --output=.env.staging
mojoclistartup --env=production --output=.env.prod

# Export to shell
export $(cat .env.local | xargs)

# Alternative: fetch to .env (interactive environment selection)
mojoclisync

# Get raw secrets (interactive environment selection)
mojocliinfisical-secrets
```

### Push Secrets
```bash
# Edit .env with your secrets first
vim .env

# Push to Infisical (interactive environment selection)
mojoclipush-secrets
```

### Project Management
```bash
# Create new project (interactive environment selection)
mojoclicreate-project --name="Project Name"
```

### Help
```bash
mojoclihelp
```

## 🔄 Complete Workflow

### Project Setup
```bash
# Initialize project with specific environment
mojocliinit --name="My Awesome Project" --env="dev"

# Fetch secrets for development
mojoclistartup --env=dev --output=.env.local

# Export to current shell
export $(cat .env.local | xargs)
```

### Development
```bash
# Edit secrets locally
vim .env

# Push changes to Infisical
mojoclipush-secrets

# Fetch latest secrets
mojoclistartup --env=dev --output=.env.local
```

### Multi-Environment Setup
```bash
# Initialize project
mojocliinit --name="My Project" --env="dev"

# Fetch secrets for different environments
mojoclistartup --env=dev --output=.env.dev
mojoclistartup --env=staging --output=.env.staging
mojoclistartup --env=production --output=.env.prod

# Use appropriate environment file
export $(cat .env.dev | xargs)      # For development
export $(cat .env.staging | xargs)  # For staging
export $(cat .env.prod | xargs)     # For production
```

## 🛠️ Local Development

### Test Commands
```bash
# Test help
php bin/mojocliclihelp

# Test init with environment
php bin/mojoclicliinit --name="Test Project" --env="dev"

# Test fetch with environment
php bin/mojocliclistartup --env=dev --output=.env.local

# Test push
php bin/mojocliclipush-secrets

# Test project creation
php bin/mojocliclicreate-project --name="New Project"
```

## 🏗️ Configuration

### Files Created
- **`infisical.json`** - Project configuration (safe to commit)
- **`.env.local`** - Fetched secrets (add to .gitignore)
- **`.env`** - Local secrets for pushing (add to .gitignore)

### Environment Variables Required
- **`INFISICAL_CLIENT_ID`** - Your Infisical client ID
- **`INFISICAL_CLIENT_SECRET`** - Your Infisical client secret

### Environment Selection
The tool supports three environments:
- **dev** (development) - Default
- **staging** - For staging environment
- **production** (or **prod**) - For production environment

Environment can be specified via `--env` parameter or selected interactively.

### Hardcoded Settings
- **API URL**: `https://secret.mojomosaic.com` (same for all environments)

## 🔒 Security Notes

- Never commit `.env` or `.env.local` files
- Set environment variables in your shell profile, not in code
- Use different client credentials for different environments
- Rotate credentials regularly
- Environment variables must be set before running any commands

## 🐛 Troubleshooting

### Authentication Errors
```bash
# Error: INFISICAL_CLIENT_ID and INFISICAL_CLIENT_SECRET must be set

# Temporary fix (current session only):
export INFISICAL_CLIENT_ID="your-actual-client-id"
export INFISICAL_CLIENT_SECRET="your-actual-client-secret"

# Permanent fix (add to shell profile):
echo 'export INFISICAL_CLIENT_ID="your-actual-client-id"' >> ~/.zshrc
echo 'export INFISICAL_CLIENT_SECRET="your-actual-client-secret"' >> ~/.zshrc
source ~/.zshrc

# Verify they're saved:
grep "INFISICAL" ~/.zshrc
```

### Environment Variables Not Loaded
```bash
# Reload your shell profile
source ~/.zshrc    # or ~/.bashrc

# Or restart your terminal
```

### Invalid Credentials (401 Error)
- Verify your credentials are correct
- Check that environment variables are properly set: `echo $INFISICAL_CLIENT_ID`
- Ensure no truncated values in your shell profile

## 📄 License

MIT