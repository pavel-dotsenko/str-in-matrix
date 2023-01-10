# SIM-command
> This is a simple command-line command for searching a "matrix" of letters\
> for the given "word".\
> > **ATTENTION!**
> > > Argument #1 (Matrix) **must** contain N<sup>2</sup> letters, where N - is the given Argument #1 (Word) **length**.


> Example usage:
>
>`./bin/run sim "AbCdEfGhI" "DeF"`


> Result output:
> 
> > Trace:  `0,1` `1,1` `2,1`
> 
> in format ...'x','y' according to the matrix X-Y axis, starting from top-left.

***By  default***, case sensitivity is **turned off**, and the ***table*** will be appended to each result found:

|     |    0    |    1    |    2    |
|:---:|:-------:|:-------:|:-------:|
|  0  |    A    |    B    |    C    |
 |  1  | ***D*** | ***E*** | ***F*** |
 |  2  |    G    |    H    |    I    |

## Setup

> Just  clone the code and run `composer install` - that's all!\
> 
> Run `./bin/run sim [matrix [word]]`,
> or enter the arguments manually then.