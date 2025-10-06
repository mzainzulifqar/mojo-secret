# Mojo CLI Setup & Distribution Guide

## 🚀 Publishing to Packagist (Composer)

### Prerequisites
- GitHub repository
- Packagist account (https://packagist.org)
- Git repository with proper tags

### Step 1: Prepare Repository
```bash
# Ensure clean working directory
git add .
git commit -m "Prepare for packagist release"
git push origin main

# Create a release tag
git tag v1.0.0
git push origin v1.0.0
```

### Step 2: Submit to Packagist
1. Go to https://packagist.org
2. Click "Submit Package"
3. Enter your GitHub repository URL: `https://github.com/yourusername/mojo-cli`
4. Click "Check"
5. If validation passes, click "Submit"

### Step 3: Set up Auto-Updates (Recommended)
1. In your GitHub repository, go to Settings > Webhooks
2. Add webhook URL: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME&apiToken=YOUR_API_TOKEN`
3. Content type: `application/json`
4. Events: Just push events

## 📦 Installation Methods

### Global Installation (Recommended)
```bash
# Install globally via Composer
composer global require mojo/cli

# Add Composer global bin to PATH (if not already done)
echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.zshrc
source ~/.zshrc

# Verify installation
mojocli help
```

### Local Project Installation
```bash
# Install in specific project
composer require mojo/cli

# Use with vendor path
./vendor/bin/mojocli help
```

### Manual Installation
```bash
# Clone repository
git clone https://github.com/yourusername/mojo-cli.git
cd mojo-cli

# Install dependencies
composer install

# Make executable
chmod +x bin/mojocli

# Add to PATH (optional)
ln -s $(pwd)/bin/mojocli /usr/local/bin/mojocli
```

## 🔧 Command Name Change: `mojo` → `mojocli`

### Update Binary Reference
1. Edit `composer.json`:
```json
{
    "bin": ["bin/mojocli"]
}
```

2. Rename executable file:
```bash
mv bin/mojo bin/mojocli
```

3. Update shebang in executable:
```bash
# Edit bin/mojocli first line to ensure it's:
#!/usr/bin/env php
```

### Update Documentation
- All README examples should use `mojocli` instead of `mojo`
- Update help text in App.php
- Update any internal references

## 🏷️ Version Management

### Creating Releases
```bash
# Update version in composer.json
# Create git tag
git tag v1.0.1
git push origin v1.0.1

# Packagist will auto-update if webhook is configured
```

### Semantic Versioning
- `v1.0.0` - Major release
- `v1.0.1` - Bug fixes
- `v1.1.0` - New features (backward compatible)
- `v2.0.0` - Breaking changes

## 📋 Pre-Release Checklist

### Code Quality
- [ ] All functions documented
- [ ] Error handling implemented
- [ ] Security review completed
- [ ] No hardcoded secrets in code

### Testing
- [ ] Test all commands manually
- [ ] Test on fresh system
- [ ] Verify environment variable handling
- [ ] Test multi-environment functionality

### Documentation
- [ ] README updated with current commands
- [ ] Installation instructions accurate
- [ ] Troubleshooting section complete
- [ ] Examples working

### Repository Hygiene
- [ ] .gitignore properly configured
- [ ] No sensitive files committed
- [ ] Clean commit history
- [ ] Proper license file

## 🌐 Distribution Workflow

### 1. Development
```bash
# Work on features
git checkout -b feature/new-command
# ... make changes ...
git commit -m "Add new command"
git push origin feature/new-command
# Create PR, merge to main
```

### 2. Release
```bash
# Checkout main and pull latest
git checkout main
git pull origin main

# Update version in composer.json
# Update CHANGELOG.md
git add .
git commit -m "Release v1.0.1"

# Create and push tag
git tag v1.0.1
git push origin main
git push origin v1.0.1
```

### 3. Verify
```bash
# Check Packagist updates automatically
# Test installation:
composer global require mojo/cli:^1.0.1
mojocli help
```

## 🔐 Security Considerations

### Package Security
- Never include credentials in published package
- Use `.gitignore` for sensitive files
- Regular dependency updates
- Security scanning with `composer audit`

### User Security
- Clear documentation about credential handling
- Environment variable best practices
- Warning about credential rotation

## 📞 Support & Maintenance

### Issue Tracking
- Use GitHub Issues for bug reports
- Provide issue templates
- Label issues appropriately

### Maintenance
- Regular dependency updates
- Security patch releases
- Backward compatibility considerations

## 🎯 Post-Publication Steps

1. **Update Documentation Sites**
   - Internal wiki/docs
   - Team communication channels

2. **Announce Release**
   - Team Slack/Discord
   - Email to stakeholders
   - Internal documentation

3. **Monitor Usage**
   - Packagist download stats
   - GitHub issue reports
   - User feedback

## 🚨 Emergency Procedures

### Critical Security Issue
1. Immediately unpublish affected versions from Packagist
2. Create emergency patch
3. Publish patched version
4. Notify all users

### Package Corruption
1. Check Packagist status
2. Re-tag and push if needed
3. Clear Composer cache: `composer clear-cache`
4. Test fresh installation