# Contributing to Idol Stage Timetable

Thank you for your interest in contributing to Idol Stage Timetable! 🌸

We welcome contributions from everyone, whether it's bug reports, feature requests, documentation improvements, or code contributions.

---

## 📑 Table of Contents

- [Code of Conduct](#-code-of-conduct)
- [How Can I Contribute?](#-how-can-i-contribute)
- [Development Setup](#-development-setup)
- [Pull Request Process](#-pull-request-process)
- [Coding Standards](#-coding-standards)
- [Commit Message Guidelines](#-commit-message-guidelines)
- [Testing](#-testing)
- [Documentation](#-documentation)
- [Contact](#-contact)

---

## 🤝 Code of Conduct

This project follows a simple code of conduct:

- **Be respectful** - Treat everyone with respect and kindness
- **Be constructive** - Provide helpful, constructive feedback
- **Be collaborative** - Work together toward common goals
- **Be inclusive** - Welcome contributors of all backgrounds and skill levels

---

## 🎯 How Can I Contribute?

### Reporting Bugs

Before creating a bug report:
1. **Check existing issues** - Your bug might already be reported
2. **Test with latest version** - Ensure bug exists in current version
3. **Gather information** - Error messages, browser console logs, etc.

**Create a bug report**:
1. Open a [new issue](https://github.com/yourusername/stage-idol-calendar/issues/new)
2. Use a clear, descriptive title
3. Describe the expected vs. actual behavior
4. Provide reproduction steps
5. Include environment details (PHP version, browser, OS)
6. Add screenshots if applicable

**Example**:
```markdown
**Bug**: Events not showing in Gantt view

**Expected**: Events display in timeline
**Actual**: Blank timeline

**Steps to Reproduce**:
1. Navigate to calendar
2. Switch to Gantt view
3. Timeline is empty

**Environment**:
- PHP 8.2
- Chrome 120
- Windows 11
```

---

### Suggesting Features

Before suggesting a feature:
1. **Check existing issues** - Feature might already be requested
2. **Consider the scope** - Should it be core feature or plugin?

**Create a feature request**:
1. Open a [new issue](https://github.com/yourusername/stage-idol-calendar/issues/new)
2. Use prefix `[Feature Request]` in title
3. Explain the use case
4. Describe the proposed solution
5. Consider alternative solutions

**Example**:
```markdown
**Feature Request**: Dark mode support

**Use Case**:
Users attending evening events prefer dark theme to reduce eye strain

**Proposed Solution**:
Add toggle button to switch between light/dark themes

**Alternatives**:
- Auto-detect from system preferences
- Time-based switching
```

---

### Improving Documentation

Documentation improvements are always welcome!

**Areas that need help**:
- Fixing typos and grammar
- Adding examples and tutorials
- Translating to other languages
- Clarifying confusing sections
- Adding screenshots/diagrams

**Process**:
1. Fork repository
2. Edit markdown files in your fork
3. Submit pull request

---

### Code Contributions

See [Development Setup](#-development-setup) and [Pull Request Process](#-pull-request-process) below.

---

## 🔧 Development Setup

### Prerequisites

- PHP 8.1+ (tested on 8.1, 8.2, 8.3) with PDO SQLite and mbstring extensions
- Git
- Text editor or IDE
- Web browser with dev tools

### Initial Setup

1. **Fork the repository** on GitHub

2. **Clone your fork**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/stage-idol-calendar.git
   cd stage-idol-calendar
   ```

3. **Add upstream remote**:
   ```bash
   git remote add upstream https://github.com/ORIGINAL_OWNER/stage-idol-calendar.git
   ```

4. **Create sample data**:
   ```bash
   # Add sample ICS files to ics/ folder
   cd tools
   php import-ics-to-sqlite.php
   cd ..
   ```

5. **Start development server**:
   ```bash
   php -S localhost:8000
   ```

6. **Open browser**: http://localhost:8000

### Development Workflow

1. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**

3. **Test thoroughly** (see [Testing](#-testing))

4. **Commit changes** (see [Commit Guidelines](#-commit-message-guidelines))

5. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Create pull request** on GitHub

---

## 🔄 Pull Request Process

### Before Submitting

- [ ] Code follows project coding standards
- [ ] All tests pass
- [ ] New features have tests
- [ ] Documentation updated if needed
- [ ] Commit messages are clear
- [ ] No merge conflicts with main branch

### Submitting PR

1. **Create pull request** on GitHub
2. **Fill out PR template** completely
3. **Link related issues** (e.g., "Fixes #123")
4. **Request review** from maintainers

### PR Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Performance improvement
- [ ] Refactoring

## Related Issues
Fixes #123

## Testing
How did you test this?

## Screenshots (if applicable)

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Tests added/updated
- [ ] Documentation updated
```

### Review Process

1. Maintainer reviews your PR
2. May request changes or clarifications
3. Make requested changes and push updates
4. Once approved, maintainer will merge

---

## 💻 Coding Standards

### PHP

- **PHP Version**: 8.1+ compatible (tested on PHP 8.1, 8.2, 8.3)
- **Formatting**: 4 spaces for indentation, no tabs
- **Naming**: camelCase for functions, UPPER_CASE for constants
- **Comments**: DocBlock comments for functions
- **Security**: Always use prepared statements, escape output

**Example**:
```php
<?php
/**
 * Get events from database
 *
 * @param string $location Venue to filter by
 * @return array Array of events
 */
function getEventsByLocation($location) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM events WHERE location = ?");
    $stmt->execute([$location]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### JavaScript

- **ES6**: Use modern JavaScript (const/let, arrow functions, etc.)
- **Formatting**: 4 spaces for indentation
- **Naming**: camelCase for variables/functions, PascalCase for classes
- **Comments**: JSDoc for complex functions
- **Security**: Never use innerHTML with user input, use textContent

**Example**:
```javascript
/**
 * Filter events by artist name
 * @param {Array} events - Array of event objects
 * @param {string} artist - Artist name to filter by
 * @returns {Array} Filtered events
 */
function filterByArtist(events, artist) {
    return events.filter(event =>
        event.categories.includes(artist)
    );
}
```

### CSS

- **Formatting**: 4 spaces for indentation
- **Naming**: kebab-case for classes
- **Organization**: Group related properties
- **Compatibility**: Test on major browsers

**Example**:
```css
.event-card {
    /* Layout */
    display: flex;
    flex-direction: column;

    /* Spacing */
    padding: 15px;
    margin: 10px 0;

    /* Visual */
    background: var(--sakura-light);
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
```

---

## 📝 Commit Message Guidelines

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code formatting (no logic changes)
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `test`: Adding/updating tests
- `chore`: Build process, dependencies

### Examples

```bash
# Simple fix
git commit -m "fix(calendar): correct date sorting in timeline view"

# Feature with body
git commit -m "feat(admin): add bulk delete for events

Admins can now select multiple events and delete them at once.
Includes confirmation dialog to prevent accidental deletions."

# Breaking change
git commit -m "feat(api): change response format

BREAKING CHANGE: API now returns events in 'data' property instead of root array.
Update client code accordingly."
```

---

## 🧪 Testing

### Manual Testing

Before submitting PR, test:

1. **Basic functionality**:
   - [ ] Events display correctly
   - [ ] Filtering works (artist, venue, search)
   - [ ] View switching (list/gantt) works
   - [ ] Export functions work

2. **Admin panel** (if changes affect it):
   - [ ] CRUD operations work
   - [ ] Request management works
   - [ ] No console errors

3. **Browsers**:
   - [ ] Chrome/Edge
   - [ ] Firefox
   - [ ] Safari
   - [ ] Mobile browsers (if UI changes)

4. **Responsive design**:
   - [ ] Desktop (1920x1080)
   - [ ] Tablet (768x1024)
   - [ ] Mobile (375x667)

### Automated Testing

The project includes **324 automated tests** covering security, cache, authentication, database operations, user management, and integration:

```bash
# Run all tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php SecurityTest          # 7 tests
php tests/run-tests.php CacheTest             # 17 tests
php tests/run-tests.php AdminAuthTest         # 38 tests
php tests/run-tests.php CreditsApiTest        # 49 tests
php tests/run-tests.php IntegrationTest       # 97 tests
php tests/run-tests.php UserManagementTest    # 116 tests
```

All tests must pass before submitting a PR. See [tests/README.md](tests/README.md) for details.

---

## 📚 Documentation

When contributing code, update relevant documentation:

### Code Changes → Update These Files

| Your Change | Update These Docs |
|-------------|-------------------|
| New feature | README.md, CHANGELOG.md |
| API changes | README.md (API section) |
| Configuration | INSTALLATION.md |
| Database schema | SQLITE_MIGRATION.md |
| Quick fix | CHANGELOG.md only |

### Documentation Standards

- **Clear and concise** - Get to the point
- **Examples** - Show don't just tell
- **Screenshots** - For UI features
- **Version info** - Note when feature was added

---

## 🔒 Security

### Reporting Security Issues

**DO NOT** open public issues for security vulnerabilities.

Instead:
1. Email security report to: [security contact email]
2. Include detailed description
3. Provide steps to reproduce
4. Suggest fix if possible

We'll respond within 48 hours and work on a fix.

### Security Best Practices

When contributing code:
- ✅ Always escape output: `htmlspecialchars()`
- ✅ Use prepared statements for SQL
- ✅ Validate and sanitize input
- ✅ Use CSRF tokens for forms
- ✅ Check file upload types/sizes
- ❌ Never trust user input
- ❌ Never use `eval()` or similar
- ❌ Never expose sensitive data

---

## 📞 Contact

- **GitHub Issues**: [Project Issues](https://github.com/yourusername/stage-idol-calendar/issues)
- **Twitter**: [@FordAntiTrust](https://x.com/FordAntiTrust)
- **Email**: [Insert contact email]

---

## 🙏 Recognition

Contributors will be:
- Listed in CHANGELOG.md for their contributions
- Mentioned in release notes
- Added to a CONTRIBUTORS file (if we create one)

---

## 📜 License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

## 🎉 Thank You!

Every contribution helps make Idol Stage Timetable better. Whether it's code, documentation, bug reports, or feature ideas - thank you for being part of this project! 🌸

---

**Happy Contributing!** 🚀

[Back to README](README.md) | [View Issues](https://github.com/yourusername/stage-idol-calendar/issues) | [View Pull Requests](https://github.com/yourusername/stage-idol-calendar/pulls)
