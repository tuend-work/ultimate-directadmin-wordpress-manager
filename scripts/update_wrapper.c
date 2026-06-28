#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <string.h>
#include <pwd.h>

int main(int argc, char *argv[]) {
    // 1. Elevate effective privileges to root
    if (setuid(0) != 0) {
        perror("Error: Failed to setuid to root");
        return 1;
    }

    // Get current executing user info
    uid_t uid = getuid();
    struct passwd *pw = getpwuid(uid);
    if (!pw) {
        perror("Error: Failed to get user info");
        return 1;
    }

    // Security check: Only root and diradmin can trigger update
    if (uid != 0 && strcmp(pw->pw_name, "diradmin") != 0) {
        fprintf(stderr, "Error: Access denied.\n");
        return 1;
    }

    const char *update_sh = "/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/scripts/self_update.sh";
    if (access(update_sh, X_OK) != 0) {
        update_sh = "./scripts/self_update.sh";
        if (access(update_sh, X_OK) != 0) {
            fprintf(stderr, "Error: self_update.sh not found or not executable.\n");
            return 1;
        }
    }

    // Execute the bash update script as root (using SUID root privileges)
    execl("/bin/bash", "bash", "-p", update_sh, NULL);
    perror("Error: execl failed");
    return 1;
}
