#!/bin/bash

function load_rcfile {
    if [ -d $1 ]; then
        for content in `\ls $1`; do
            if [ -d $1 ]; then
                load_rcfile $1/$content
            elif [ -f $1 ]; then
                source $1/$content
            fi
        done
    elif [ -f $1 ]; then
        source $1
    fi
}

export PATH="$HOME/bin:/usr/local/bin:/bin:/usr/bin:\
/usr/games:/usr/X11R6/bin:/sbin:/usr/sbin:/usr/local/sbin:/stand\
:/usr/local/jdk1.1.8/bin"

export EDITOR=vim

LS_COLORS='no=00:fi=00:di=01;34;43:ln=02;36:pi=40;33:so=01;35:bd=40;33;01:cd=40;33;01:or=40;31;01:ex=01;32:*.tar=01;31:*.tgz=01;31:*.arj=01;31:*.taz=01;31:*.lzh=01;31:*.zip=01;31:*.z=01;31:*.Z=01;31:*.gz=01;31:*.deb=01;31:*.jpg=01;35:*.gif=01;35:*.bmp=01;35:*.ppm=01;35:*.tga=01;35:*.xbm=01;35:*.xpm=01;35:*.tif=01;35:*.mpg=01;37:*.avi=01;37:*.gl=01;37:*.dl=01;37:';
export LS_COLORS

alias  ls='gnuls --color=always --show-control-chars';
alias  ll='ls -alF';
#alias  telnet='/usr/local/bin/zh-telnet';
alias  db='mysql -h dev-2 -u carolinesun -p ';
alias  ba='bash';
alias  ex='exit';
alias  grep='grep --color';
alias  c~='cd ~';
alias  c-='cd -';
alias  c.='cd ..';
#alias ports='portupgrade -acCuv -m "WITH_CHARSET=big5 SKIP_INSTALL_DB=yes"';

#git
function git_branch {
    ref=$(git symbolic-ref HEAD 2> /dev/null) || return;
    echo "("${ref#refs/heads/}")";
}

function git_since_last_commit {
    now=`date +%s`;
    last_commit=$(git log --pretty=format:%at -1 2> /dev/null) || return;
    seconds_since_last_commit=$((now-last_commit));
    minutes_since_last_commit=$((seconds_since_last_commit/60));
    hours_since_last_commit=$((minutes_since_last_commit/60));
    minutes_since_last_commit=$((minutes_since_last_commit%60));

    echo "${hours_since_last_commit}h${minutes_since_last_commit}m ";
}

PS1="dev5_[\[\033[1;32m\]\w\[\033[0m\]] \[\033[0m\]\[\033[1;36m\]\$(git_branch)\[\033[0;33m\]\$(git_since_last_commit)\[\033[0m\]$ "

#PS1="_vMdev5_[\u]:\w\[\033[1;36m\]\$(git_branch)\[\033[0m\]\$";
#PS1="dev5_[\u]:\w\[\033[1;36m\]\$(git_branch)\[\033[0m\]\$";
#PS1="\h \W";
export PS1

#For colorful man pages
export LESS_TERMCAP_mb=$'\E[01;31m'
export LESS_TERMCAP_md=$'\E[01;31m'
export LESS_TERMCAP_me=$'\E[0m'
export LESS_TERMCAP_se=$'\E[0m'
export LESS_TERMCAP_so=$'\E[01;44;33m'
export LESS_TERMCAP_ue=$'\E[0m'
export LESS_TERMCAP_us=$'\E{01;32m'


