#!/bin/bash
#
# Boot native CLI
#
unset HISTFILE;
export DYLD_FALLBACK_LIBRARY_PATH="/Applications/XAMPP/ds-plugins/dev-cli/library/platform/mac/macports/lib:/Applications/XAMPP/xamppfiles/lib:/usr/lib"
export PATH="/Applications/XAMPP/ds-plugins/dev-cli/library/platform/mac/macports/bin:/Applications/XAMPP/xamppfiles/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:/opt/X11/bin:$PATH"
export TERMINFO="/Applications/XAMPP/ds-plugins/dev-cli/library/platform/mac/macports/share/terminfo"
unset DYLD_LIBRARY_PATH
"$@"
