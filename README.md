# Git Version Panel

Panel for Tracy debug panel.
Shows current branch and current revision hash to be able identify your deployed version on sight.

## Installing

Install library via composer:

```
composer require vipkwd/tracy-gitversion-panel
```


## Registerring...

### In older versions of Nette (< 2.2)

```
nette:
    debugger:
        bar:
            - Vipkwd\Tracy\GitVersionPanel
```

### In newer versions of Nette (>= 2.2)

```
tracy:
    bar:
        - Vipkwd\Tracy\GitVersionPanel
```

### In pure Tracy

```
use Vipkwd\Tracy\GitVersionPanel;
Tracy\Debugger::getBar()->addPanel(new GitVersionPanel());
```