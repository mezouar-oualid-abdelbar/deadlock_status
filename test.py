import pandas as pd

df = pd.read_csv('datacopy.csv')

print("Description: \n ", df.describe())
print("Null values:\n", df.isnull().sum())
print("Data types:\n", df.dtypes)
print("rows count :\n" ,df.count())
df = df.fillna({'mvp_rank':4}) 
print("Null values:\n", df.isnull().sum())

print(df.head(5)) 
 
 